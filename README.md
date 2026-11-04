# Subtotal 4.1.0

Gestion des textes prédéfinis directement depuis les documents, avec un droit dédié.

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

Sur les fiches, le récapitulatif est regroupé dans une seule cellule : **Sous-total : titre | Prix de revient : valeur | Marge : valeur (taux %) | Montant HT : valeur**. Le titre correspond au niveau du sous-total. Le prix de revient et la marge apparaissent lorsque leur affichage est activé. Le taux de marge est calculé sur le prix de revient ; il n’est pas affiché lorsque ce coût est nul ou négatif. Le soulignage du titre ne s’applique pas à la marge.

Le sélecteur de titre permet d’insérer une ligne directement après un titre ou en fin de section selon le réglage choisi. En fin de section, une section sans sous-total se termine au titre suivant de niveau égal ou supérieur. Les libellés longs restent consultables dans un sélecteur de largeur bornée.

## Textes prédéfinis depuis les documents

Dans « Ajouter un texte libre », les deux sélecteurs utilisent le Select2 natif Dolibarr lorsqu’il est activé. Le « + » avant « ou saisir une description » ouvre une seconde modale de gestion : recherche, pagination, création, modification, activation et suppression confirmée. Le libellé est obligatoire (255 caractères maximum) ; le contenu reste facultatif. L’éditeur HTML natif est utilisé lorsqu’il est disponible et activé, avec une zone de texte sinon.

Après fermeture, les choix sont actualisés sans effacer la description personnalisée ni changer le bloc choisi. Un modèle ajouté n’est pas sélectionné automatiquement. Seul un choix explicite charge son contenu dans l’éditeur ; « OK » copie le texte final dans le document. Les modifications ultérieures du dictionnaire ne changent pas les documents existants.

Le nettoyage HTML conserve la mise en forme textuelle, les listes et les tableaux ; les styles CSS, liens et ressources embarquées sont retirés lors de la copie ou de l’enregistrement.

Après mise à jour des fichiers, actualisez les définitions natives des droits en ouvrant les permissions d’un utilisateur ou d’un groupe (Dolibarr enregistre les droits manquants du module actif). Attribuez **Gérer les textes prédéfinis** (`10477701`) aux utilisateurs ou groupes concernés ; ce droit n’est pas attribué par défaut. Le « + » exige aussi un utilisateur interne et les droits de lecture/modification du document brouillon. L’utilisation des modèles existants ne nécessite pas ce droit. Une réactivation native du module reste possible et conserve ses réglages.

Le dictionnaire administratif existant et sa table sont conservés. Les textes restent propres à l’entité courante, même si le document est partagé depuis une autre entité. Les enregistrements concurrents sont refusés ; la saisie est conservée pour permettre sa reprise.

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
