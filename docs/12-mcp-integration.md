# MCP Integration (AI Assistants)

Media Library Tools lets AI assistants that speak the Model Context Protocol (MCP) search your media library, read media details, and update media text fields on your behalf. It has been tested with Claude Code and the official MCP Inspector.

## Overview

The integration lets an AI assistant:
- Search the media library (by text, MIME type, or attachments missing alt text)
- Read the details of one attachment
- Update an attachment's title, alt text, caption, and description

None of the three tools can rename, move, upload, or delete files. Connected to the recommended dedicated endpoint, an assistant cannot reach anything outside these three tools.

**Requirements**:
- WordPress **6.9 or newer** (the Abilities API is part of WordPress core from 6.9)
- The official **MCP Adapter** plugin from [wordpress.org/plugins/mcp-adapter](https://wordpress.org/plugins/mcp-adapter/) — tested with **0.7.0**. The dedicated endpoint uses the adapter's documented `create_server()` API, whose signature is unchanged since 0.3.0, but versions before 0.7.0 have not been tested
- A WordPress **administrator** account (the `manage_options` capability)

Without the MCP Adapter, or on WordPress older than 6.9, nothing is registered and the plugin behaves exactly as before.

## Available Tools

| Tool | What it does | Changes data |
|---|---|---|
| `tsmlt-search-media` | Lists matching attachments, one page at a time (up to 50 per page) | No |
| `tsmlt-get-media-details` | Returns one attachment's title, alt text, caption, description, URL, MIME type, file name, dimensions, file size, image sizes, attached post, and upload date | No |
| `tsmlt-update-media-metadata` | Changes only the fields you send: title, alt text, caption, description. An empty string clears a field | Yes |

**What is never returned**: server file paths, raw attachment metadata, EXIF data (including GPS location), or custom fields.

**What is never changed**: the file itself, its name or URL, the attached post, or any other attachment.

## Two Endpoints — Use the Dedicated One

**Recommended setup: connect your assistant to the dedicated Media Library Tools endpoint.**

Once the MCP Adapter is active, your site has two MCP endpoints:

| Endpoint | URL | Exposes |
|---|---|---|
| **Media Library Tools (recommended)** | `https://your-site.com/wp-json/media-library-tools/mcp` | Only the three Media Library Tools tools above. Administrators only (`manage_options`) |
| MCP Adapter default server | `https://your-site.com/wp-json/mcp/mcp-adapter-default-server` | Every public ability from **every** plugin on the site, including abilities that change or delete data |

### Why the dedicated endpoint is safer

The MCP Adapter's default server is a gateway: its *discover* and *execute* tools reach every ability any plugin registers as public — not only Media Library Tools. Other plugins' abilities can be write-capable: on a WooCommerce site, for example, they include abilities that create, update, or delete products and change order status. An assistant connected to the default server with an administrator password can use all of them. Any logged-in user can also list those abilities and their input schemas there, although each ability still checks its own permissions before it runs.

The dedicated endpoint limits exposure to the three Media Library Tools abilities:
- It lists only those three tools. Calls to any other tool — including the MCP Adapter's own discover/execute gateway tools — are refused with "Tool not found".
- It requires an administrator account (`manage_options`) before a session can even start, so other roles cannot list the tools.
- Each tool still runs every check described under [Access Control](#access-control).

**Important**: The dedicated endpoint does not change or disable the default server. Media Library Tools' own tools also remain available there, alongside other plugins' abilities. If nothing on your site needs the default server, a developer can disable it with the MCP Adapter's `mcp_adapter_create_default_server` filter — check with any other integration you use first.

## Step-by-Step Setup

### 1. Install the MCP Adapter

1. Go to **Plugins → Add New**
2. Search for **MCP Adapter** (author: WordPress.org)
3. Click **Install Now**, then **Activate**

### 2. Create an Application Password

1. Go to **Users → Profile** for an administrator account
2. Scroll to **Application Passwords**
3. Enter a name such as `Claude Code – laptop` and click **Add New Application Password**
4. Copy the password shown — it is displayed only once

**Tips**:
- Create one password per assistant and per device, so you can revoke one without affecting the others
- Never paste the password into an AI chat; enter it only into the client's configuration
- WordPress only offers Application Passwords over HTTPS, or on sites marked as a local development environment

### 3. Connect your MCP client

#### Claude Code

Run this in your own terminal (not inside a chat):

```bash
read -rs WP_APP_PW   # paste the Application Password; nothing is shown
claude mcp add --transport http media-library-tools \
  https://your-site.com/wp-json/media-library-tools/mcp \
  --header "Authorization: Basic $(printf '%s:%s' 'YOUR_USERNAME' "$WP_APP_PW" | base64 | tr -d '\n')"
unset WP_APP_PW
```

Restart Claude Code, then run `claude mcp list` — the server should show **Connected**.

#### Clients that use a JSON configuration file (not yet tested)

The MCP Adapter documents the `@automattic/mcp-wordpress-remote` helper for these clients. Point it at the dedicated endpoint (this route has not yet been tested against the dedicated endpoint; Claude Code above has):

```json
{
  "mcpServers": {
    "media-library-tools": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://your-site.com/wp-json/media-library-tools/mcp",
        "WP_API_USERNAME": "YOUR_USERNAME",
        "WP_API_PASSWORD": "your application password"
      }
    }
  }
}
```

### 4. Try it

Ask your assistant something like:
- "Find images in the media library that have no alt text."
- "Show me the details of attachment 1234."
- "Write alt text for attachment 1234 and save it."

## Access Control

On the dedicated endpoint:

| Who | Result |
|---|---|
| Administrators | Can use all three tools |
| Editors, authors, and other roles | Refused before any tool is listed (HTTP 403) |
| Unauthenticated requests (no or invalid Application Password) | Refused (HTTP 401) |

Every tool call also re-checks `manage_options` and the permission to read (or, for updates, edit) the specific attachment — on either endpoint. Tools only work with attachments in WordPress's normal `inherit` status; trashed and private attachments are reported as not found.

## Important Notes

### Review before saving
`tsmlt-update-media-metadata` overwrites the current value and cannot be undone. The tool is marked as destructive so clients can ask before running it — keep your client's tool-approval prompts turned on.

### Revoking access
Delete the Application Password under **Users → Profile → Application Passwords**. The assistant loses access immediately.

### Multisite
Each site in a network has its own endpoints, and access requires `manage_options` on that site. Multisite has not yet been tested.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `404` from `/wp-json/media-library-tools/mcp` | MCP Adapter not active, or WordPress older than 6.9 | Activate the MCP Adapter on WordPress 6.9+ |
| `401 Unauthorized` | Missing or wrong Application Password, or wrong username | Recreate the password and update the client configuration |
| `403 Forbidden` | The account is not an administrator | Use an administrator account |
| "Tool not found" | The tool belongs to another plugin, or is an MCP Adapter gateway tool | Expected — the dedicated endpoint only offers the three tools above |
| Schema warnings in MCP Inspector about `get-media-details` | Some output fields can be `null`, written as a type list (valid JSON Schema 2020-12, which MCP uses by default) | Informational; no action needed |
