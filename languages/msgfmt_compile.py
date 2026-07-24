#!/usr/bin/env python3
import sys, os, struct, ast

def parse_po(path):
    entries = {}
    with open(path, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    msgid = None
    msgstr = None
    collecting = None
    fuzzy = False
    for raw in lines:
        line = raw.rstrip('\n')
        if line.startswith('#,') and 'fuzzy' in line:
            fuzzy = True
        if line.startswith('msgid '):
            collecting = 'msgid'
            try:
                msgid = ast.literal_eval(line[6:])
            except Exception:
                msgid = ''
        elif line.startswith('msgstr '):
            collecting = 'msgstr'
            try:
                msgstr = ast.literal_eval(line[7:])
            except Exception:
                msgstr = ''
        elif line.startswith('"'):
            try:
                txt = ast.literal_eval(line)
            except Exception:
                txt = ''
            if collecting == 'msgid' and msgid is not None:
                msgid += txt
            elif collecting == 'msgstr' and msgstr is not None:
                msgstr += txt
        else:
            # blank or comment ends entry
            if msgid is not None and msgstr is not None:
                if not fuzzy:
                    entries[msgid] = msgstr
                msgid = None
                msgstr = None
                collecting = None
                fuzzy = False
    # final check
    if msgid is not None and msgstr is not None and not fuzzy:
        entries[msgid] = msgstr
    return entries


def write_mo(catalog, output_path):
    # catalog: dict msgid->msgstr
    messages = sorted(catalog.items(), key=lambda x: x[0])
    ids = [m[0].encode('utf-8') for m in messages]
    strs = [m[1].encode('utf-8') for m in messages]

    # create binary blocks
    id_data = b''.join([i + b'\x00' for i in ids])
    str_data = b''.join([s + b'\x00' for s in strs])

    n = len(ids)
    # offsets
    off_orig = 7 * 4
    off_trans = off_orig + n * 8
    # table of contents end
    start_of_strings = off_trans + n * 8

    # compute original offsets
    id_offsets = []
    pos = 0
    for i in ids:
        id_offsets.append(pos)
        pos += len(i) + 1

    str_offsets = []
    pos = 0
    for s in strs:
        str_offsets.append(pos)
        pos += len(s) + 1

    with open(output_path, 'wb') as of:
        # header
        of.write(struct.pack('Iiiiiii', 0x950412de, 0, n, off_orig, off_trans, 0, 0))
        # original table
        for i, b in enumerate(ids):
            of.write(struct.pack('II', len(b), start_of_strings + id_offsets[i]))
        # translation table
        for i, s in enumerate(strs):
            of.write(struct.pack('II', len(s), start_of_strings + len(id_data) + str_offsets[i]))
        # string pool
        of.write(id_data)
        of.write(str_data)


if __name__ == '__main__':
    if len(sys.argv) != 3:
        print('Usage: msgfmt_compile.py input.po output.mo')
        sys.exit(2)
    po = sys.argv[1]
    mo = sys.argv[2]
    if not os.path.exists(po):
        print('Input PO not found:', po)
        sys.exit(1)
    catalog = parse_po(po)
    write_mo(catalog, mo)
    print('Wrote', mo)
