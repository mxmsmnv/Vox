# Vox

Vox adds community discussions to ProcessWire pages: reviews, questions and answers, open threads, replies, ratings, moderation, photos and user reputation.

![Vox](assets/Vox.png)

It is made for sites where conversation belongs next to the content itself: product pages, catalogs, articles, collections, directories, knowledge bases and editorial projects.

**Author:** Maxim Semenov  
**Website:** [smnv.org](https://smnv.org)  
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

## What Vox Does

- Adds reviews with star or dot ratings and optional custom fields.
- Adds Q&A with best-answer selection.
- Adds open discussions and nested replies.
- Adds comment panels for specific blocks on a page.
- Supports guest posting with optional email requirements.
- Supports photo attachments.
- Includes moderation queues, reports and stop-word filtering.
- Includes points, ranks, badges and leaderboards.
- Provides a ProcessWire admin area for managing all community content.
- Includes an optional complete demo section with restaurant, hotel, product-experience, Answers mode, profile and inline-form sample data.

## Admin Area

Vox adds a dedicated admin section where site editors can:

- review recent activity;
- browse, filter and edit entries;
- approve, reject or remove pending content;
- handle reports;
- configure review fields per template;
- manage ranks, badges and point rules;
- maintain stop-word lists;
- view embed guidance.

## Public Widgets

Vox includes ready-to-use public views for:

- ratings and reviews;
- questions and answers;
- Answers mode for Q&A-platform style pages;
- discussions;
- forum landing pages;
- modular user profile sections;
- inline block comments.

You can place one widget on a page or combine several into tabs.

Vox also includes a Textformatter, so editors can embed widgets, profile sections or inline posting forms in formatted text fields with tokens such as `[[vox:forum]]`, `[[vox:answers]]`, `[[vox:profile]]`, `[[vox:reviews]]`, `[[vox:form]]` or `[[vox:all]]`.

## Admin interface language

The admin area's own interface (config screen, labels, buttons) ships with
ready-made translations for 29 European languages — Bulgarian, Croatian,
Czech, Danish, Dutch, Estonian, Finnish, French, German, Greek, Hungarian,
Irish, Italian, Latvian, Lithuanian, Maltese, Norwegian, Polish, Portuguese,
Romanian, Russian, Serbian, Slovak, Slovenian, Spanish, Swedish, Turkish and
Ukrainian — following ProcessWire's standard module-translation mechanism
(`languages/*.csv`).

To install one: **Setup > Modules > Vox > "install translations"** (link
appears once Language Support is installed and at least one non-default
language page exists) → pick the CSV for each target language → Submit. The
admin then sees the Vox admin area in their own PW admin language
automatically. Requires ProcessWire's core **Language Support** module — see
[processwire.com/modules/language-support](https://processwire.com/modules/language-support/).

## Installation

1. Copy the `Vox` folder into `/site/modules/`.
2. In ProcessWire Admin, refresh modules.
3. Install `Vox`.
4. Open the Vox admin section and adjust the settings.
5. Use the Embed screen to add Vox widgets to your templates or install the optional demo.

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for setup, configuration and template integration.

See [CHANGELOG.md](CHANGELOG.md) for the release notes.

## Author

Maxim Semenov  
[smnv.org](https://smnv.org)  
[maxim@smnv.org](mailto:maxim@smnv.org)

## License

MIT
