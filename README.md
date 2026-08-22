# RSS

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/stezkoy/flarum-feeds.svg)](https://packagist.org/packages/stezkoy/flarum-feeds) [![Total Downloads](https://img.shields.io/packagist/dt/stezkoy/flarum-feeds.svg)](https://packagist.org/packages/stezkoy/flarum-feeds)

A Flarum 2.x extension that aggregates RSS/Atom feeds, displays blog articles in a dedicated feed page or the main discussion list, and lets users start native Flarum discussions from RSS articles only when comments are needed.

[Read the Russian documentation](README.ru.md)

## Features

- Manage RSS/Atom sources in the admin panel.
- Review user-submitted RSS sources before they become public.
- Bind each RSS source to a Flarum user. When an RSS article becomes a real discussion, that user is used as the discussion author.
- Preview a source in the admin panel by fetching the live RSS/Atom feed in real time.
- Add a blank row at the bottom of the admin source list for quick entry.
- Display RSS articles on a dedicated "Independent Blogs" page.
- Optionally mix RSS articles into the homepage discussion list by publish time.
- Add homepage toolbar buttons for switching between the mixed RSS list and the native discussion list, plus submitting a new RSS source.
- Add a per-user setting so each member can choose whether the homepage defaults to RSS mixed mode.
- Keep RSS articles out of the Flarum discussions table until a user comments.
- Provide a virtual RSS article page before the first comment. The page uses native discussion-like UI and shows a link to the original article as the first post content.
- Create a real Flarum discussion lazily when the first comment is posted, linking it back to the RSS item.
- Assign configured Flarum tags to lazily created RSS discussions.
- Store RSS items in a separate `rss_items` table to avoid duplicating external articles as discussions before interaction.
- Include English and Chinese language files.

## Requirements

- Flarum `^2.0.0-beta`
- PHP `^8.3`
- Composer

## Installation

Install with Composer:

```sh
composer require stezkoy/flarum-feeds:"^3.0"
php flarum migrate
php flarum cache:clear
```

Enable the extension from the Flarum admin panel.

## Updating

```sh
composer update stezkoy/flarum-feeds:"^3.0"
php flarum migrate
php flarum cache:clear
```

If your forum still requires an old package name, switch the Composer requirement first:

```sh
composer remove stezkoy/rss stezkoy/flarum-rss --no-update
composer require stezkoy/flarum-feeds:"^3.0" -W
```

## Admin Configuration

Open the RSS extension page in the admin panel.

Available settings:

- **Show RSS articles in the homepage list**: when enabled, RSS articles can be mixed into the main homepage list.
- **Tag for RSS discussions**: the tag used when an RSS article is converted into a real Flarum discussion after the first comment.

The source table supports:

- RSS source title and URL editing.
- Approval status toggle.
- Bound user avatar, with support for changing or removing the bound user.
- Real-time source preview.
- Source deletion.
- A permanent blank row for adding the next source.

User-submitted RSS sources are pending by default and must be approved by an admin before their articles are shown publicly.

## Forum Usage

When homepage RSS mode is enabled globally, the homepage toolbar includes:

- A switch button to toggle between the RSS mixed list and the native Flarum discussion list.
- An add button that opens the RSS source submission modal.

Users also get an **RSS** section on `/settings`. The **Show RSS articles on the homepage** switch controls that user's default homepage mode:

- On: the homepage defaults to the RSS mixed list.
- Off: the homepage defaults to the native Flarum discussion list.

## RSS Discussions and Comments

RSS articles are not inserted into Flarum's `discussions` table just because they were fetched.

Instead, this extension keeps fetched articles in `rss_items`. In the list view they look like discussions, but they stay separate until a user comments.

Before the first comment:

- The comment count links to a virtual RSS article page.
- The page uses native Flarum-style discussion UI.
- The first post content is a labeled link to the original article.

After the first comment:

- A real Flarum discussion is created.
- The configured tags are applied.
- The RSS source's bound user becomes the discussion and first-post author.
- The user's comment is saved as a normal Flarum comment post.
- Future clicks go to the real discussion.

This keeps the forum database clean while still allowing community discussion around RSS articles.

## Fetching Feeds

The extension registers the `rss:fetch` command.

Fetch all approved RSS sources:

```sh
php flarum rss:fetch
```

Test a single URL without storing it:

```sh
php flarum rss:fetch "https://example.com/feed.xml"
```

The command is scheduled hourly through Flarum's scheduler. Make sure your server runs the Flarum scheduler, for example:

```sh
* * * * * cd /path/to/flarum && php flarum schedule:run >> /dev/null 2>&1
```

## Upgrading From Older Versions

Version `2.0.0` and later targets Flarum 2.x. If you are upgrading from an older Flarum 1.x installation, keep your existing database tables and run migrations:

```sh
composer require stezkoy/flarum-feeds:"^3.0"
php flarum migrate
php flarum cache:clear
```

Existing RSS source and item data is reused by the Flarum 2.x version.

## Links

- [Packagist](https://packagist.org/packages/stezkoy/flarum-feeds)
- [GitHub](https://github.com/Stezkoy/flarum-feeds)
