

# Set of semantic types to use during concept extraction by QuickUMLS. Only concepts belonging to these types will be returned by QuickUMLS.
REQUIRED_SEMTYPES = {

        # Disorders
    'T020',     # Acquired Abnormality
    'T190',     # Anatomical Abnormality
    'T049',     # Cell or Molecular Dysfunction
    'T019',     # Congenital Abnormality
    'T047',     # Disease or Syndrome
    'T050',     # Experimental Model of Disease
    'T037',     # Injury or Poisoning
    'T048',     # Mental or Behavioral Dysfunction
    'T191',     # Neoplastic Process
    'T046',     # Pathologic Function

        # Symptoms
    'T184',     # Sign or Symptom
    'T033',     # Finding

        # Treatments
    'T058',     # Health Care Activity
    'T061',     # Therapeutic or Preventive Procedure
    'T121',     # Pharmacologic Substance

        # Tests
    'T059',     # Laboratory Procedure
    'T060',     # Diagnostic Procedure
}

# Dictionary to look up which category a semantic type belongs to.
REQUIRED_SEMTYPES_CATEGORY_MAP = {

    'T020' : 'disorders',     # Acquired Abnormality
    'T190' : 'disorders',     # Anatomical Abnormality
    'T049' : 'disorders',     # Cell or Molecular Dysfunction
    'T019' : 'disorders',     # Congenital Abnormality
    'T047' : 'disorders',     # Disease or Syndrome
    'T050' : 'disorders',     # Experimental Model of Disease
    'T037' : 'disorders',     # Injury or Poisoning
    'T048' : 'disorders',     # Mental or Behavioral Dysfunction
    'T191' : 'disorders',     # Neoplastic Process
    'T046' : 'disorders',     # Pathologic Function

    'T184' : 'symptoms',     # Sign or Symptom
    'T033' : 'symptoms',     # Finding

    'T058' : 'treatments',  # Health Care Activity
    'T061' : 'treatments',  # Therapeutic or Preventive Procedure
    'T121' : 'treatments',  # Pharmacologic Substance

    'T059' : 'tests',       # Laboratory Procedure
    'T060' : 'tests'        # Diagnostic Procedure
}