import json,subprocess,sys,pathlib
base=sys.argv[1];scope=sys.argv[2] if len(sys.argv)>2 else 'pages'
root=pathlib.Path(__file__).resolve().parents[1]
rules=json.loads((root/'deploy/module-boundaries.json').read_text())
changed=set(subprocess.check_output(['git','diff','--name-only',base,'HEAD'],cwd=root,text=True).splitlines())
protected=set(rules['protectedVoiceSources'])|{'deploy/voice-central-patches.json'}
if changed&protected and scope!='voice':
 raise SystemExit('Voice sources changed: explicit voice scope and real audio validation required: '+', '.join(sorted(changed&protected)))
routes=json.loads((root/rules['router']).read_text())
assert len({r['file'] for r in routes.values()})==len(routes)
assert all(r['file']==name+'.php' and (root/'pages'/r['file']).is_file() for name,r in routes.items())
assert (root/'index.php').stat().st_size<2500
print('MODULE_BOUNDARIES_OK')
