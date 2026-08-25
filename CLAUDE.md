
## Tests

`php tests/run.php` (10 tests), ou `phpunit --no-configuration tests/` — les mêmes fichiers.
Le greffon injecte un script tiers dans **chaque page** de GLPI ; les tests portent sur ses
deux garde-fous : le refus d'une URL de conteneur qui ne serait pas en HTTPS, et
l'échappement de cette URL dans `mtm-config.js`. Ce dernier est vérifié par relecture — la
valeur écrite doit se redécoder à l'identique — et non par la présence de caractères, parce
qu'une chaîne échappée contient les mêmes caractères que celle qui ne l'est pas.
