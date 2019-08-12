# Modified version to be base around SNOMED CT concept IDs instead of cuis - Ivan


from __future__ import unicode_literals, division, print_function

# built in modules
import os
import sys
import time
import codecs
import shutil
import argparse
from six.moves import input

# project modules
from toolbox import countlines, CuiSemTypesDB, SimstringDBWriter, mkdir
from constants import HEADERS_MRCONSO, HEADERS_MRSTY, LANGUAGES

try:
    from unidecode import unidecode
except ImportError:
    pass


def get_semantic_types(path, headers):
    sem_types = {}
    with codecs.open(path, encoding='utf-8') as f:
        for i, ln in enumerate(f):
            content = dict(zip(headers, ln.strip().split('|')))

            sem_types.setdefault(content['cui'], []).append(content['sty'])

    return sem_types


def get_mrconso_iterator(path, headers, lang='ENG'):
    with codecs.open(path, encoding='utf-8') as f:
        for i, ln in enumerate(f):
            content = dict(zip(headers, ln.strip().split('|')))

            if content['lat'] != lang:
                continue

            yield content


def extract_from_mrconso(
        mrconso_path, mrsty_path, opts,
        mrconso_header=HEADERS_MRCONSO, mrsty_header=HEADERS_MRSTY):

    start = time.time()
    print('loading semantic types...', end=' ')
    sys.stdout.flush()
    sem_types = get_semantic_types(mrsty_path, mrsty_header)
    print('done in {:.2f} s'.format(time.time() - start))

    start = time.time()

    mrconso_iterator = get_mrconso_iterator(
        mrconso_path, mrconso_header, opts.language
    )

    total = countlines(mrconso_path)

    processed = set()
    yielded = set()
    put_aside = set()
    code_concepts = 0
    i = 0
    added_from_suppressed = 0

    for content in mrconso_iterator:
        i += 1

        if i % 100000 == 0:
            delta = time.time() - start
            status = (
                '{:,} in {:.2f} s ({:.2%}, {:.2f} ms / term)'
                ''.format(i, delta, i / total, delta / i * 1000 if i > 0 else 0)
            )
            print(status)

        concept_text = content['str'].strip()
        cui = content['cui']
        code = content['code']
        preferred = 1 if content['ispref'] == 'Y' else 0
        suppressed = content['suppress']

        if opts.lowercase:
            concept_text = concept_text.lower()

        if opts.normalize_unicode:
            concept_text = unidecode(concept_text)

        code_concept_yielded = (code, concept_text) in processed
        if code_concept_yielded:
            continue

        # check that code is not obsolete before adding it to iterator
        # if code is obsolete, check whether it's been yielded earlier as active
        # if it was, then term can be added to the database
        # if not, then store it till the end, recheck then and yield if ok

        code_yielded = code in yielded
        if suppressed != 'N':
            if not code_yielded:
                #print((concept_text, code, cui, sem_types[cui], preferred))
                put_aside.add((concept_text, code, cui, tuple(sem_types[cui]), preferred))
                continue
            else:
                added_from_suppressed += 1
        processed.add((code, concept_text))
        code_concepts += 1
        if not code_yielded:
            yielded.add(code)
        yield (concept_text, code, cui, sem_types[cui], preferred)

    # Now check the put aside codes if they were yielded as active
    # If yes, yield them
    print("Concepts put aside: " + str(len(put_aside)))
    rejected = 0
    with open('codes_rejected.txt', 'w') as rejected_file:
        with open('codes_added.txt', 'w') as added_file:
            for (concept_text, code, cui, semtypes, preferred) in put_aside:
                if code in yielded and (code, concept_text) not in processed:
                    processed.add((code, concept_text))
                    code_concepts += 1
                    yield (concept_text, code, cui, list(semtypes), preferred)
                    added_from_suppressed += 1
                    added_file.write(str((concept_text, code, cui, preferred)) +"\n")
                    continue
                rejected += 1
                rejected_file.write(str((concept_text, code, cui, preferred)) +"\n")
    print("Terms added from suppressed: " + str(added_from_suppressed))
    print("Terms rejected from put aside: " + str(rejected))
    print("Total number of code_concept combinations added to db: " + str(code_concepts))

    delta = time.time() - start
    status = (
        '\nCOMPLETED: {:,} in {:,} s ({:.2f} ms / term)'
        ''.format(i, delta, delta / i * 1000 if i > 0 else 0)
    )
    print(status)


def parse_and_encode_ngrams(extracted_it, simstring_dir, cuisty_dir):
    # Create destination directories for the two databases
    mkdir(simstring_dir)
    mkdir(cuisty_dir)

    ss_db = SimstringDBWriter(simstring_dir)
    cuisty_db = CuiSemTypesDB(cuisty_dir)

    simstring_terms = set()

    for i, (term, code, cui, stys, preferred) in enumerate(extracted_it, start=1):
        if term not in simstring_terms:
            ss_db.insert(term)
            simstring_terms.add(term)

        cuisty_db.insert(term, code, cui, stys, preferred)


def driver(opts):
    if not os.path.exists(opts.destination_path):
        msg = ('Directory "{}" does not exists; should I create it? [y/N] '
               ''.format(opts.destination_path))
        create = input(msg).lower().strip() == 'y'

        if create:
            os.makedirs(opts.destination_path)
        else:
            print('Aborting.')
            exit(1)

    if len(os.listdir(opts.destination_path)) > 0:
        msg = ('Directory "{}" is not empty; should I empty it? [y/N] '
               ''.format(opts.destination_path))
        empty = input(msg).lower().strip() == 'y'
        if empty:
            shutil.rmtree(opts.destination_path)
            os.mkdir(opts.destination_path)
        else:
            print('Aborting.')
            exit(1)

    if opts.normalize_unicode:
        try:
            unidecode
        except NameError:
            err = ('`unidecode` is needed for unicode normalization'
                   'please install it via the `[sudo] pip install '
                   'unidecode` command.')
            print(err, file=sys.stderr)
            exit(1)

        flag_fp = os.path.join(opts.destination_path, 'normalize-unicode.flag')
        open(flag_fp, 'w').close()

    if opts.lowercase:
        flag_fp = os.path.join(opts.destination_path, 'lowercase.flag')
        open(flag_fp, 'w').close()

    flag_fp = os.path.join(opts.destination_path, 'language.flag')
    with open(flag_fp, 'w') as f:
        f.write(opts.language)

    mrconso_path = os.path.join(opts.umls_installation_path, 'MRCONSO.RRF')
    mrsty_path = os.path.join(opts.umls_installation_path, 'MRSTY.RRF')

    mrconso_iterator = extract_from_mrconso(mrconso_path, mrsty_path, opts)

    simstring_dir = os.path.join(opts.destination_path, 'umls-simstring.db')
    cuisty_dir = os.path.join(opts.destination_path, 'cui-semtypes.db')

    parse_and_encode_ngrams(mrconso_iterator, simstring_dir, cuisty_dir)


if __name__ == '__main__':
    ap = argparse.ArgumentParser()
    ap.add_argument(
        'umls_installation_path',
        help=('Location of UMLS installation files (`MRCONSO.RRF` and '
              '`MRSTY.RRF` files)')
    )
    ap.add_argument(
        'destination_path',
        help='Location where the necessary QuickUMLS files are installed'
    )
    ap.add_argument(
        '-L', '--lowercase', action='store_true',
        help='Consider only lowercase version of tokens'
    )
    ap.add_argument(
        '-U', '--normalize-unicode', action='store_true',
        help='Normalize unicode strings to their closest ASCII representation'
    )
    ap.add_argument(
        '-E', '--language', default='ENG', choices=LANGUAGES,
        help='Extract concepts of the specified language'
    )
    opts = ap.parse_args()

    driver(opts)    #added indent to this line, not sure why it worked before - IL
