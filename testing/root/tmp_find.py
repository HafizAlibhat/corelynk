import os
import fnmatch
matches = []
for root, dirs, files in os.walk('app'):
    for name in fnmatch.filter(files, '*.php'):
        path = os.path.join(root, name)
        with open(path, encoding='utf-8', errors='ignore') as fh:
            text = fh.read()
        if 'document_type' in text and 'vendor_payment' in text:
            matches.append(path)
with open('tmp_matches.txt','w',encoding='utf-8') as out:
    for m in matches:
        out.write(m + '\n')
print(len(matches))
