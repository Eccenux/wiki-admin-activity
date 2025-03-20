## Must
- ✅PoC getting admin data.
- ✅Ns:0 edits.
- ✅Cache for ns:0 data (slow as in extra ~10s).
- ✅Update host naming conventions: https://wikitech.wikimedia.org/wiki/Help:Wiki_Replicas#Connecting_to_the_database_replicas
- Check for given admin/user:
	- Add links in the table (`details.php?username=encod($...)`).
	- From username get actor_id.
	- Get same table.
- Detailed checks for user:
	- → README.md ←
	- For each log etc show min/max timestamps.

## Should
- ✅Some layout/structure (.tpl.php?) + CSS.
- ✅Basic i18n.
- ✅Some sortable-table library.
- ✅Icon: image by Jules78120 based on work by Alphos, Booyabazooka, and Essjay.

## Maybe
- Prpepare a list of projects (for autocomplete or validation)?
	- https://wikitech.wikimedia.org/wiki/Help:Wiki_Replicas#Connecting_to_the_database_replicas
	- https://db-names.toolforge.org/
- Project from GET.
	- Cache per dbname.
	- Simple form?
	- $_GET/$_REQ (cookies welcome?) + validate as [a-z]{2,10}(!)
- Some kind of i18n?
