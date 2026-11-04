# Subtotal 4.0.2

Version corrective en préparation depuis le **2026-11-04**, non publiée.

Subtotal ajoute des titres, sous-titres, textes libres et sous-totaux aux propositions commerciales, commandes, factures et documents fournisseurs pris en charge par Dolibarr.

## Fonctionnalités

- organisation des lignes en blocs déplaçables ;
- niveaux de titres et de sous-totaux ;
- affichage des quantités, remises et marges par bloc ;
- gestion des blocs « Non compris » ;
- récapitulatifs PDF par titre ;
- prise en charge des factures de situation ;
- dictionnaire de textes libres isolé par entité ;
- API REST de lecture des sous-totaux.

Pour les factures de situation, le rendu des titres, textes libres et sous-totaux suit le nombre réel de colonnes de la table Dolibarr. La marge d’un sous-total additionne la contribution de chaque ligne au prorata de son avancement dans la situation courante.

Sur les devis, commandes et factures ordinaires, la marge du sous-total utilise le calcul natif Dolibarr sur les lignes du bloc concerné. Les coûts portés par le document, y compris zéro, restent prioritaires sur le coût actuel du catalogue. Les blocs imbriqués suivent le même périmètre que le montant HT du sous-total.

Sur les fiches, les valeurs sont libellées dans cet ordre : **Sous-total : titre | Prix de revient : valeur | Marge : valeur | Montant HT : valeur**. Le titre correspond au niveau du sous-total. Le prix de revient et la marge apparaissent lorsque leur affichage est activé ; le montant HT conserve sa colonne native.

Le sélecteur de titre permet d’insérer une ligne directement après un titre ou en fin de section selon le réglage choisi. En fin de section, une section sans sous-total se termine au titre suivant de niveau égal ou supérieur. Les libellés longs restent consultables dans un sélecteur de largeur bornée.

## Compatibilité

- Dolibarr 16 ou version ultérieure ;
- PHP 7.0 ou version ultérieure ;
- Dolibarr 20+ et PHP 8.0+ recommandés.

La matrice détaillée est disponible dans l’onglet **Compatibilité** des réglages du module.

## Installation

Placez le répertoire `subtotal` dans le répertoire des modules externes de Dolibarr, puis activez **Sous-Total** depuis la liste des modules. Les réglages sont accessibles depuis l’unique roue dentée du module.

La désactivation conserve les constantes et choix administrateur. Elle ne purge ni les réglages ni les lignes historiques identifiées par le `special_code` `104777`.

## Maintenance

Les anciennes migrations web ont été remplacées par des commandes CLI en lecture seule par défaut dans `scripts/maintenance/`. Utilisez `--execute` uniquement après sauvegarde et précisez toujours l’entité ciblée. Les migrations objet exigent également `--user-id=<administrateur>`.

## Sécurité et Multicompany

Les actions Ajax et l’API contrôlent le module, les droits natifs du document parent, son entité et le périmètre des utilisateurs externes. Les PDF et récapitulatifs sont écrits dans le répertoire documentaire de l’entité propriétaire.

## Licence

GNU GPL v3 ou ultérieure.
