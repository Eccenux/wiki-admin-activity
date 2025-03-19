## Must
- ✅PoC getting admin data.
- ✅Ns:0 edits.
- ✅Cache for ns:0 data (slow as in extra ~10s).
- ✅Update host naming conventions: https://wikitech.wikimedia.org/wiki/Help:Wiki_Replicas#Connecting_to_the_database_replicas
- Check for given admin/user:
	- Add links in the table (`details.php?username=encod($...)`).
	- From username get actor_id.
	- Get same tabel.
	- Get same tabel.
- Detailed checks for user:
	- → README.md ←

## Should
- Some layout/structure (.tpl.php?) + CSS.
- Some sortable-table library.

## Maybe
- List of projects?
- Project from GET.
	- Cache per dbname.
	- Simple form?
	- $_GET/$_REQ (cookies welcome?) + validate as [a-z]{2,10}(!)
- Some kind of i18n?
