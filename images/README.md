# Visuels AlloPrint

- `photos/` : neuf photos d’illustration en WebP, avec une variante `-640.webp` pour les petits écrans. Les dimensions et descriptions sont dans `photos/manifest.json`.
- `photos/sources/` : les neuf originaux PNG générés, en pleine définition.
- `photos/prompts.json` : prompts exacts et provenance des images (outil intégré `image_gen`).
- `icons/` : seize pictogrammes SVG indépendants, fond transparent, modifiables dans un éditeur vectoriel. Chaque service possède un fichier portant son nom ; les autres couvrent devis, validation, finitions et contact.
- `icons-3d/` : neuf icônes 3D des services (WebP + PNG 512 × 512, fond transparent), générées avec ElevenLabs. Classes CSS : `icon3d--card` (cartes), `icon3d--list` (accueil), `icon3d--hero` (pages de services).
- `realisations/` : photographies clients d’origine.

Les visuels générés illustrent les métiers et supports ; ils ne documentent pas de vraies commandes clients. Voir aussi `../CREDITS.md`.

Depuis la racine du projet, `python outils/preparer-visuels.py` prépare les WebP à partir des PNG (Pillow requis). `python outils/generer-pages.py` reconstruit les pages de services et de contact avec les photos et icônes intégrées. L’accueil reste édité dans `site/index.html`.

Exemple d’icône décorative à côté d’un texte :

```html
<img class="m-picto" src="images/icons/impression-numerique.svg"
     width="32" height="32" alt="" aria-hidden="true">
```

L’icône reste décorative lorsqu’un libellé décrit déjà sa fonction. Si elle est utilisée seule, fournir un texte alternatif approprié.
