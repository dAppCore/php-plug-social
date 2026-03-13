# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP library (`lthn/php-plug-social`) providing social media provider integrations for the Plug framework. Licensed under EUPL-1.2. Requires PHP 8.2+ and depends on `lthn/php` (the core Plug framework).

## Commands

```bash
composer install          # Install dependencies
```

No tests, linter, or build steps exist in this repository yet.

## Architecture

**Namespace:** `Core\Plug\Social\` (PSR-4 autoloaded from `src/`)

### Provider Structure

Each social provider lives in its own subdirectory under `src/` and follows a consistent class pattern:

| Class | Contract | Purpose |
|-------|----------|---------|
| `Auth` | `Authenticable` (+ `Refreshable` if supported) | OAuth2 flow: auth URL, token exchange, account info |
| `Post` | `Postable` | Publishing content via `publish(string $text, Collection $media, array $params)` |
| `Read` | `Readable` | Fetching content via `get(string $id)` and `list(array $params)` |
| `Delete` | `Deletable` | Removing content via `delete(string $id)` |
| `Media` | `MediaUploadable` | Uploading media via `upload(array $item)` |

Some providers have extra classes: `Pages` (Meta, LinkedIn), `Boards` (Pinterest), `Groups` (VK), `Subreddits` (Reddit), `Comment` (YouTube).

### Providers

Twitter, Meta (Facebook/Instagram), LinkedIn, Pinterest, Reddit, TikTok, VK, YouTube.

### Shared Traits and Contracts (from `lthn/php`)

All provider classes compose three traits from the core framework:

- **`BuildsResponse`** — wraps API responses into `Core\Plug\Response` objects via `fromHttp()` and `error()`
- **`ManagesTokens`** — provides `withToken()`, `accessToken()`, `getToken()` for OAuth token handling
- **`UsesHttp`** — provides `http()` (returns Laravel HTTP client) and `buildUrl()` for URL construction

Contracts (`Authenticable`, `Refreshable`, `Postable`, `Readable`, `Deletable`, `MediaUploadable`) define the interface each class implements.

### Conventions

- All classes use `declare(strict_types=1)`
- API base URLs are stored as `private const API_URL`
- Auth classes have static `identifier()` (slug) and `name()` (display name) methods
- Post/Auth classes provide `externalPostUrl()` / `externalAccountUrl()` static helpers for building user-facing URLs
- Token expiry is stored as a UTC timestamp via `Carbon::now('UTC')->addSeconds()`
- Media upload happens inline during `Post::publish()` — the Post class instantiates Media, uploads each item, collects IDs, then publishes
- Provider-specific headers (e.g. LinkedIn's `X-Restli-Protocol-Version`, Reddit's `User-Agent`) are defined in `httpHeaders()` / `redditHeaders()` methods
