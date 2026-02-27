# Nimtara Connect — Plugin Documentation

Connect any WordPress site to the [Nimtara](https://github.com/dkingfx/nimtara) AI content platform. Once installed, Nimtara can publish AI-generated articles directly to your WordPress site as drafts or live posts.

---

## Requirements

- WordPress 6.0 or later
- PHP 8.1 or later
- A running Nimtara instance

---

## Installation

### Step 1 — Download the plugin

Go to [github.com/dkingfx/nimtara-connect](https://github.com/dkingfx/nimtara-connect), click **Code → Download ZIP**.

### Step 2 — Install on WordPress

1. Log in to your WordPress admin panel
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the downloaded ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Step 3 — Get your connection details

Go to **Settings → Nimtara Connect**. You will see:

| Field | Example |
|-------|---------|
| Submit Endpoint | `https://yoursite.com/wp-json/nimtara/v1/submit` |
| API Key | `a8f3kx29mzqt...` |

Keep this page open — you'll need both values in the next step.

### Step 4 — Add a WordPress connection in Nimtara

1. In Nimtara, go to **Settings → Connections**
2. Click **+ New Connection**
3. Set the type to **WordPress**
4. Enter your WordPress site URL (e.g. `https://yoursite.com`)
5. Paste the API Key from the plugin settings page
6. Click **Add**

The connection is now active. Any pipeline run assigned to this connection will publish to your WordPress site.

---

## How Articles Are Published

When a Nimtara pipeline completes, it sends the article to your WordPress site:

```
Nimtara pipeline finishes
        │
        ▼
POST /wp-json/nimtara/v1/submit
X-Nimtara-Key: <your-api-key>
        │
        ▼
Plugin creates a WordPress post
 • Title, content, and excerpt set
 • Featured image downloaded and added to Media Library
 • Pillar mapped to a WordPress category
 • Tags created if they don't exist
 • Post saved as Draft (or Published)
        │
        ▼
Post appears in WordPress → Posts
```

### Publish modes

| Mode | Result |
|------|--------|
| `draft` | Post saved as a draft — requires manual publish by an editor |
| `publish` | Post goes live immediately |

Publish mode is set per pipeline run in Nimtara.

### Pillar → Category mapping

Nimtara assigns each article a **pillar** (e.g. `models`, `agents`, `compute`). The plugin maps this to a WordPress category:

1. Checks the **category map** configured in plugin settings (slug → category ID)
2. Falls back to finding a category with a matching slug
3. If **Auto-create categories** is enabled, creates the category automatically
4. Otherwise falls back to the WordPress default category

---

## Plugin Settings

Go to **Settings → Nimtara Connect** to configure:

### API Key

Auto-generated on activation. Click **Regenerate** to invalidate the old key and create a new one. Update the key in Nimtara immediately after regenerating.

### Callback URL *(optional)*

A Nimtara webhook URL that the plugin calls when a post status changes. Lets Nimtara know when a draft has been published by an editor.

Format: `https://your-nimtara-instance.com/api/webhooks/publish`

### Callback Key *(optional)*

Sent as `X-Nimtara-Key` in callback requests so Nimtara can authenticate them.

### Auto-create Categories

When enabled, the plugin automatically creates a new WordPress category if the incoming pillar slug doesn't match any existing category. Useful for getting started without manual category setup.

---

## REST API Reference

All endpoints require the `X-Nimtara-Key` header.

### `POST /wp-json/nimtara/v1/submit`

Submit an article for publishing.

**Request body (JSON):**

```json
{
  "title": "Article title",
  "content": "<p>HTML content...</p>",
  "excerpt": "Short summary shown in feeds",
  "pillar": "agents",
  "tags": ["LangGraph", "CrewAI"],
  "featured_image": {
    "src": "https://example.com/image.jpg",
    "alt": "Image description"
  },
  "author": "Editorial Staff",
  "source": "Nimtara",
  "publish_mode": "draft",
  "nimtara_run_id": "pr_abc123"
}
```

**Response (201 Created):**

```json
{
  "post_id": 42,
  "status": "draft",
  "url": "https://yoursite.com/?p=42",
  "edit_url": "https://yoursite.com/wp-admin/post.php?post=42&action=edit"
}
```

---

### `GET /wp-json/nimtara/v1/status`

Check connection health.

**Response:**

```json
{
  "status": "connected",
  "site": "My Blog",
  "url": "https://yoursite.com",
  "wp_version": "6.7",
  "plugin_version": "1.0.0"
}
```

---

### `GET /wp-json/nimtara/v1/posts/:id`

Get the current status of a published post.

**Response:**

```json
{
  "post_id": 42,
  "status": "publish",
  "url": "https://yoursite.com/article-slug/",
  "title": "Article title",
  "modified": "2026-02-26 14:30:00"
}
```

---

## Viewing Submissions

Go to **Settings → Nimtara Connect** and scroll to **Recent Submissions** to see all posts created by Nimtara, their current status, and a direct edit link.

Each Nimtara-created post stores the originating run ID as post metadata (`_nimtara_run_id`), making it easy to trace any post back to the pipeline run that created it.

---

## Troubleshooting

**Articles not appearing in WordPress**
- Confirm the API key in Nimtara matches the one in **Settings → Nimtara Connect**
- Check that the WordPress REST API is accessible (`/wp-json/` should return JSON)
- Some security plugins (Wordfence, iThemes) block custom REST routes — add a whitelist rule for `/wp-json/nimtara/`

**Featured image not uploading**
- The WordPress user account must have `upload_files` capability
- The featured image URL must be publicly accessible (not behind auth)
- Check WordPress file upload limits (`upload_max_filesize` in `php.ini`)

**Category not being assigned**
- Enable **Auto-create categories** in plugin settings, or
- Manually create a category with a slug matching the Nimtara pillar (e.g. `agents`, `compute`)

**Regenerated API key — Nimtara still using old key**
- Update the API key in Nimtara: **Settings → Connections → [your WP connection] → Edit**
