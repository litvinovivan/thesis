import semtypes
import argparse
import json
from collections import defaultdict
import codecs
from pathlib import Path
from operator import itemgetter

# for http requests to get concept children or concept name
import urllib.request, urllib.error, urllib.parse

from tqdm import tqdm
from glob import glob
import os

# url to get descendants of a SNOMED CT concept
EXPAND_URL = "https://ontoserver.csiro.au/stu3-latest/ValueSet/$expand?url=http://snomed.info/sct?fhir_vs=isa/"

# url to get preferred name of a SNOMED CT concept (NOT the fully specified one)
LOOKUP_URL = "https://ontoserver.csiro.au/stu3-latest/CodeSystem/$lookup?system=http://snomed.info/sct&property=display&code="
display_names = {}

from client import get_quickumls_client
matcher = get_quickumls_client()

# from quickumls import QuickUMLS
# matcher = QuickUMLS('c:/quickumls-data-with-suppressed', overlapping_criteria='length', window=10, threshold=0.8, accepted_semtypes=semtypes.REQUIRED_SEMTYPES)


def load_display_names():
    try:
        with open('display_names.json', 'r') as f:
            return json.load(f)
    except FileNotFoundError:
        print('No existing display_names.json file')
        return {}


def save_display_names(dict):
    with open('display_names.json', 'w') as f:
        json.dump(dict, f)
        # add trailing newline for POSIX compatibility
        f.write('\n')


def get_json(url):
    opener = urllib.request.build_opener()
    return json.loads(opener.open(url).read())


# code has to be string
def get_descendants(code):
    concepts = {}
    try:
        expanded = get_json(EXPAND_URL + code)
    # if code doesn't exist?
    except urllib.error.HTTPError:
        return concepts
    if expanded['expansion']['total'] > 1:
        for concept in expanded['expansion']['contains']:
            concepts[concept['code']] = concept['display']
    return concepts

"""
Look up preferred concept name for a SNOMED code from Ontoserver
"""
def get_display_name(code):
    try:
        response = get_json(LOOKUP_URL + code)
    # if code is not found, it will error
    except urllib.error.HTTPError:
        print(f"Code {code} not found in Ontoserver")
        return None
    except urllib.error.URLError:
        print('No internet connection to contact Ontoserver')
        return ""
    return response['parameter'][0]['valueString']


def annotate_text_json(text):
    return json.dumps(annotate_text(text))


def annotate_text_with_descendants_json(text):
    return json.dumps(annotate_text_with_descendants(text))


def annotate_text_with_descendants(text):
    concepts = annotate_text(text)
    result = {}
    for code, term in concepts.items():
        result[code] = term
        descendants = get_descendants(code)
        for id, name in descendants.items():
            result[id] = name
    return result

"""
Concept extraction from text used during text annotation
"""
def annotate_text(text):
    concepts = {}
    # phrase is a list of dicts
    for phrase in matcher.match(text, best_match=True, ignore_syntax=False):
        # reversing so that term with a higher similarity is returned for a repeated code
        for annotation in reversed(phrase):
            concepts[annotation['code']] = annotation['term']
    return concepts

"""
Concept extraction from text used during file annotation
"""
def annotate_with_spans(text):
    spans = defaultdict(list)
    added = set()
    for phrase in matcher.match(text, best_match=True, ignore_syntax=False):
        for annotation in phrase:
            # add span/code combinations skipping duplicates (higher similarity term is stored)
            if (annotation['start'], annotation['end'], annotation['code']) not in added:
                spans[(annotation['start'], annotation['end'])].append((annotation['code'], annotation['term'], annotation['semtypes']))
                added.add((annotation['start'], annotation['end'], annotation['code']))
    return spans

"""
Inserts extracted concepts into original plain text to conform to Elasticsearch's Mapper Annotated Text plugin. 
Example original text: "Melanoma is a malignant tumor of melanocytes which are found predominantly in skin but also in the bowel and the eye."
Expected result (depending on selected semtypes): "[Melanoma](202092003) [is a malignant tumor](363346000) of melanocytes which are found predominantly in skin but also in the [bowel](113276009) and the [eye](244486005&81745001)."
"""
def add_markup(text, annotations):
    result = ""
    cursor = 0
    for (start, end), values in sorted(annotations.items()):
        # add original text from beginning and in between annotations
        result += text[cursor:start]
        # add original span in square brackets that was recognised as concept(s)
        result += f"[{text[start:end]}]"
        # add codes from values into round brackets joined by & if more than 1
        result += f"({'&'.join(str(code) for code, term, semtypes in values)})"
        cursor = end
    # add the rest of original text if any
    if cursor < len(text):
        result += text[cursor:]
    return result


"""
Create json file with annotations.
sections: original text, marked-up text, disorders, symptoms, treatments, tests, filename.
Each medical category section will have nested list: snomed_code, name, count.
"""
def annotate_doc_with_markup_json(filename):
    print("\nAnnotating " + filename)
    # codecs.open with utf-8-sig removes the Encoding header (BON) characters from the fist line of text if present
    with codecs.open(filename, 'r', 'utf-8-sig') as fd:
        doc = {
            'original_text' : '',
            'marked_up_text' : '',
            'filename' : '',
            'disorders' : [],
            'symptoms' : [],
            'treatments' : [],
            'tests' : []
        }
        doc['filename'] = Path(filename).name
        concept_list = []
        for line in fd:
            stripped = line.strip()
            doc['original_text'] = doc['original_text'] + stripped + '\n'
            spans = annotate_with_spans(stripped)
            if len(spans) > 0:
                marked_up = add_markup(stripped, spans)
                # save spans for future processing
                for x in spans.values():
                    concept_list += x
            else:
                marked_up = stripped
            doc['marked_up_text'] = doc['marked_up_text'] + marked_up + '\n'

    # get disorders, symptoms, treatments, tests from spans
    concepts = defaultdict(dict)
    previous_code = None
    code_in_diff_cats_count = 0
    prev_categories = set()
    global display_names
    display_names_needs_saving = False
    if not display_names:
        display_names = load_display_names()

    for code, term, sem_types in sorted(concept_list, key=itemgetter(0)):
        if code != previous_code:
            prev_categories = set()
            previous_code = code

        # get preferred name for uniformity
        pref_name = ""

        if code in display_names:
            pref_name = display_names[code]
        else:
            name = get_display_name(code)
            if name:
                pref_name = name
            else:   # if name == "" or name == None:
                pref_name = term
            display_names[code] = pref_name
            display_names_needs_saving = True

        # add code to all relevant categories as per semantic types
        for semtype in sem_types:
            try:
                category = semtypes.REQUIRED_SEMTYPES_CATEGORY_MAP[semtype]
            # semtype not part of required categories
            except:
                continue
            if category not in prev_categories:
                if prev_categories:
                    code_in_diff_cats_count += 1
                    print(f"Code {code} is in more than one category")
                    with open('multicategory.txt', 'a') as mc:
                        mc.write(f"{code} in: {', '.join(cat for cat in prev_categories)} and {category}\n")
                prev_categories.add(category)
                concepts[category][code] = {}
                concepts[category][code]['count'] = 1
                concepts[category][code]['name'] = pref_name
            else:
                concepts[category][code]['count'] += 1

    print(f"Total number of codes in more than one category is: {code_in_diff_cats_count}.")

    # update display_names file if needed
    if display_names_needs_saving:
        save_display_names(display_names)

    # Build json doc
    for category in concepts.keys():
        doc[category] = [ {
                'code' : code,
                'name' : concepts[category][code]['name'],
                'count' : concepts[category][code]['count']
                }
                for code in concepts[category].keys()]

    with open('annotated_txt/annotated_' + Path(filename).name.strip('.txt') + '.json', 'w') as outfile:
        json.dump(doc, outfile)
        # add trailing newline for POSIX compatibility
        outfile.write('\n')
    print("Annotating finished for " + filename)


"""
Creates a marked-up version of a text file. Saves the marked up version as txt file. 
"""
def annotate_doc_with_markup(filename):
    print("Annotating " + filename)
    # codecs.open with utf-8-sig removes the Encoding header (BON) characters from the fist line of text if present
    with codecs.open(filename, 'r', 'utf-8-sig') as fd:
        doc = {'marked_up': ''}
        for line in fd:
            stripped = line.strip()
            spans = annotate_with_spans(stripped)
            if len(spans) > 0:
                marked_up = add_markup(stripped, spans)
            else:
                marked_up = stripped
            doc['marked_up'] = doc['marked_up'] + marked_up + '\n'

    with open('annotated_txt/annotated_' + Path(filename).name, 'w') as outfile:
        outfile.write(doc['marked_up'])
    print("Annotating finished for " + filename)


# Removes annotation mark-up from elasticsearch result highlights, adds <em> tags around hit terms.
# Not used anywhere.
def remove_markup(text):
    # find the hits, encapsulate them in <em>s
    strings = text.partition("](_hit_term=")
    while strings[1]:
            # separate the hit term
        front = strings[0].rpartition("[")
            # add <em> tags around hit term
        beginning = front[0] + f"<em>{front[2]}</em>"
            #get rid of annotations of the hit term
        end = strings[2].partition(")")
        result = beginning + end[2]
        strings = result.partition("](_hit_term=")  #this is a tuple

    #get rid of all other annotation markup
    strings = strings[0].partition("](")
    while strings[1]:
            #separate the term in [ ]
        front = strings[0].rpartition("[")
        beginning = front[0] + front[2]
            #get rid of annotations of the term in [ ]
        end = strings[2].partition(")")
        result = beginning + end[2]
        strings = result.partition("](")
    result = strings[0]
    return result


if __name__ == '__main__':
    '''
    Annotate free-text documents with SNOMED CT and format in JSON.
    '''
    parser = argparse.ArgumentParser(description="Annotate free-text documents with UMLS and format in JSON.")
    parser.add_argument('-d', '--doc_dir', help='Directory to process.')
    parser.add_argument('-f', '--file', help='File to process.')
    parser.add_argument('text', help='Text to annotate.', nargs='?')
    parser.add_argument('-c', '--text_with_descendants', help='Text to annotate including descendants')
    args = parser.parse_args()

    if args.doc_dir:
        if not os.path.exists('annotated_'+args.doc_dir):
            os.makedirs('annotated_'+args.doc_dir)
        for filename in tqdm(glob('{}/*'.format(args.doc_dir))):
            if filename.endswith('.json'):
                continue
            annotate_doc_with_markup_json(filename)
    elif args.file:
        annotate_doc_with_markup_json(args.file)
    elif args.text:
        print(annotate_text_json(args.text))
    elif args.text_with_descendants:
        print(annotate_text_with_descendants_json(args.text_with_descendants))
    else:
        parser.print_usage()