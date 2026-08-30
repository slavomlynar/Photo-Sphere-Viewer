=== Client Document Vault ===
Contributors: (doplňte)
Tags: klienti, dokumenty, upload, účtovníctvo, súkromné súbory
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Súkromný "trezor" na výmenu dokumentov medzi účtovnou spoločnosťou a jej klientmi priamo vo WordPress administrácii.

== Popis ==

Client Document Vault umožňuje vašim klientom po prihlásení do administrácie
WordPressu nahrávať podklady (faktúry, výpisy, mzdové podklady a pod.) vo
formátoch PDF, obrázky (JPG/PNG/GIF/WEBP) a Excel/CSV, do svojho súkromného
priečinka, ktorý nevidí žiadny iný klient.

Pracovníci účtovnej spoločnosti (napr. administrátori) vidia prehľad
všetkých klientov, môžu si stiahnuť ich podklady, nahrať dokument v mene
klienta alebo poslať klientovi hotový dokument naspäť.

= Hlavné vlastnosti =

* Vlastná rola "Klient" - po prihlásení vidí iba svoje dokumenty, žiadne iné
  časti administrácie.
* Nahrávanie cez AJAX (bez nutnosti prekliknúť sa cez viacero stránok).
* Whitelist povolených typov súborov (PDF, JPG, PNG, GIF, WEBP, XLS, XLSX,
  CSV) + kontrola skutočného obsahu súboru (nielen prípony).
* Súbory sa neukladajú do verejnej Media Library, ale do chráneného
  priečinka mimo priameho prístupu z webu.
* Každý klient má priečinok pod náhodným, neuhádnuteľným identifikátorom -
  súbory sa vždy sťahujú cez PHP s kontrolou oprávnení, nikdy priamou URL.
* E-mailové upozornenie účtovnej spoločnosti pri novom podklade od klienta,
  a upozornenie klientovi, keď mu pracovník pošle dokument naspäť.
* Kategórie a obdobie (napr. "08/2026") pri každom dokumente pre ľahšiu
  organizáciu.
* Nastaviteľná maximálna veľkosť súboru a zoznam kategórií.
* Dokumenty môže trvalo zmazať iba pracovník (audit trail podkladov).

= Bezpečnosť - dôležité upozornenie k serveru =

Plugin sám vytvorí priečinok `wp-content/uploads/cdv-private/` a do neho
zapíše `.htaccess` s pravidlom `Require all denied` (funguje na Apache).
Pokiaľ váš web beží na **Nginx**, `.htaccess` sa neuplatní - je potrebné do
konfigurácie servera pridať vlastné pravidlo, napríklad:

`
location ^~ /wp-content/uploads/cdv-private/ {
    deny all;
    return 404;
}
`

Bez tohto nastavenia na Nginx serveroch odporúčame pred nasadením overiť,
že priečinok naozaj nie je dostupný priamo cez URL
(`https://vas-web.sk/wp-content/uploads/cdv-private/`).

== Inštalácia ==

1. Nahrajte priečinok `client-document-vault` do `wp-content/plugins/`.
2. Aktivujte plugin v administrácii (Pluginy → Client Document Vault).
3. Choďte do **Klientske dokumenty → Nastavenia** a upravte kategórie,
   maximálnu veľkosť súboru a e-mail na notifikácie.
4. Vytvorte používateľské účty pre klientov (Používatelia → Pridať nového)
   a priraďte im rolu **Klient**.
5. Klient sa prihlási bežne cez `/wp-login.php` - po prihlásení uvidí iba
   položku menu "Moje dokumenty".

== Časté otázky ==

= Vidia klienti navzájom svoje dokumenty? =

Nie. Každý klient vidí a nahráva výhradne do svojho vlastného priečinka.
Prístup k cudziemu dokumentu (napr. uhádnutím ID v URL) je blokovaný na
úrovni PHP pri každom stiahnutí.

= Môže klient zmazať svoj dokument? =

Nie, zámerne. Aby zostala zachovaná história odovzdaných podkladov (dôležité
pre účtovné/audítorské účely), dokumenty môže trvalo zmazať iba pracovník
účtovnej spoločnosti.

= Dá sa to napojiť na Dropbox/Google Drive? =

Aktuálna verzia ukladá súbory priamo na server (wp-content/uploads).
Napojenie na externé úložisko (S3, Google Drive) je možné doplniť v
budúcej verzii - ozvite sa, ak je to pre vás dôležité.

== Changelog ==

= 1.0.0 =
* Prvé vydanie.
