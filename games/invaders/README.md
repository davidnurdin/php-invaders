# invaders

Jeu de tir fixe JumPHP : une flotte de 55 envahisseurs descend vers le sol, détruisez-la avant qu'elle
n'atterrisse. Boucliers destructibles au pixel, soucoupe mystère, vagues de plus en plus rapides,
vie bonus à 1500 points, mode démo.

Depuis la racine du dépôt :

```bash
bin/jumphp install invaders   # dépendances
bin/jumphp run invaders       # jouer sur le bureau
bin/jumphp test invaders      # vérifications + test automatique sans écran
bin/jumphp build invaders     # version navigateur -> dist/web
bin/jumphp serve invaders     # http://localhost:8080
```

Commandes : flèches / A-D pour bouger, Espace (ou Haut / W / Z) pour tirer, Échap ou P pour la pause.
