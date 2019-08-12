"""
This file is run by the search app to extract concepts from user's search query 
(optionally with descendant concepts). Returns a json string.
"""
import argparse

from extract import annotate_text_with_descendants_json, annotate_text_json

if __name__ == '__main__':
    '''
    Extract SNOMED CT concepts from plain text.
    '''
    parser = argparse.ArgumentParser(description="Annotate free-text documents with UMLS and format in JSON.")
    parser.add_argument('text', help='Text to annotate.', nargs='?')
    parser.add_argument('-c', '--text_with_descendants', help='Text to annotate including descendants')
    args = parser.parse_args()

    if args.text:
        print(annotate_text_json(args.text))

    elif args.text_with_descendants:
        print(annotate_text_with_descendants_json(args.text_with_descendants))

    else:
        parser.print_usage()