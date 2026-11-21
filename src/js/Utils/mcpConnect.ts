/**
 * MCP page: client definitions, their configuration and the setup prompt.
 *
 * Every client connects to the WordPress default MCP server with HTTP Basic
 * authentication (WordPress username and Application Password). The
 * formats follow each client's own documentation:
 * - Claude Code: `claude mcp add --transport http <name> <url> --header …`,
 *   or an `.mcp.json` entry `{ type: "http", url, headers }`.
 * - Claude Desktop: the `mcp-remote` helper (Node.js 18+), with the header
 *   value in `env` (mcp-remote's workaround for spaces in arguments).
 * - Codex: `~/.codex/config.toml`, `[mcp_servers.<name>]` with `url` and
 *   `http_headers`.
 * - Cursor: `~/.cursor/mcp.json`, `{ url, headers }`.
 */

/** Credentials and connection details from the server. */
export interface McpCredentials {
    username: string;
    /** Application Password, in WordPress's chunked form. */
    password: string;
    /** base64( username:password ) for the Authorization header. */
    encodedCredentials: string;
    serverUrl: string;
    serverName: string;
}

export type McpClientId = 'claude-code' | 'claude-desktop' | 'codex' | 'cursor' | 'other';

/** One client's instructions. */
export interface McpClient {
    id: McpClientId;
    label: string;
    /** Where the configuration goes, or '' when a command is used instead. */
    configPath: { unix: string; windows: string };
    format: 'json' | 'toml' | 'shell';
    requiresNode: boolean;
}

export const MCP_CLIENTS: McpClient[] = [
    { id: 'claude-code', label: 'Claude Code', configPath: { unix: '', windows: '' }, format: 'shell', requiresNode: false },
    { id: 'claude-desktop', label: 'Claude Desktop', configPath: { unix: '~/Library/Application Support/Claude/claude_desktop_config.json', windows: '%APPDATA%\\Claude\\claude_desktop_config.json' }, format: 'json', requiresNode: true },
    { id: 'codex', label: 'Codex', configPath: { unix: '~/.codex/config.toml', windows: '%USERPROFILE%\\.codex\\config.toml' }, format: 'toml', requiresNode: false },
    { id: 'cursor', label: 'Cursor', configPath: { unix: '~/.cursor/mcp.json', windows: '%USERPROFILE%\\.cursor\\mcp.json' }, format: 'json', requiresNode: false },
    { id: 'other', label: 'Other', configPath: { unix: '', windows: '' }, format: 'json', requiresNode: false },
];

/** A client by ID (Claude Code when unknown). */
export const mcpClient = (id: string): McpClient => MCP_CLIENTS.find((client) => client.id === id) ?? MCP_CLIENTS[0];

/** Quote a TOML basic string. */
const tomlString = (value: string): string => `"${value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;

/** Quote a POSIX shell argument. */
const shellArg = (value: string): string => `'${value.replace(/'/g, `'\\''`)}'`;

/** The Authorization header value. */
const basic = (credentials: McpCredentials): string => `Basic ${credentials.encodedCredentials}`;

/** The configuration (or command) a client needs. */
export const clientConfig = (id: McpClientId, credentials: McpCredentials): string => {
    const name = credentials.serverName;
    switch (id) {
        case 'claude-code':
            return `claude mcp add --transport http ${shellArg(name)} ${shellArg(credentials.serverUrl)} --header ${shellArg(`Authorization: ${basic(credentials)}`)}`;
        case 'claude-desktop':
            return JSON.stringify({
                mcpServers: {
                    [name]: {
                        command: 'npx',
                        args: ['-y', 'mcp-remote', credentials.serverUrl, '--header', 'Authorization:${AUTH_HEADER}'],
                        env: { AUTH_HEADER: basic(credentials) },
                    },
                },
            }, null, 2);
        case 'codex':
            return `[mcp_servers.${tomlString(name)}]\nurl = ${tomlString(credentials.serverUrl)}\nhttp_headers = { "Authorization" = ${tomlString(basic(credentials))} }\n`;
        case 'cursor':
            return JSON.stringify({ mcpServers: { [name]: { url: credentials.serverUrl, headers: { Authorization: basic(credentials) } } } }, null, 2);
        default:
            return JSON.stringify({ mcpServers: { [name]: { type: 'http', url: credentials.serverUrl, headers: { Authorization: basic(credentials) } } } }, null, 2);
    }
};

/** The prompt to paste into the client so it sets up the connection itself. */
export const setupPrompt = (id: McpClientId, credentials: McpCredentials): string => {
    const client = mcpClient(id);
    const config = clientConfig(id, credentials);
    const details = [
        'I want to add this WordPress site\'s Media Library Tools as an MCP server to this AI client.',
        '',
        'Connection details:',
        `- Server URL: ${credentials.serverUrl}`,
        `- Username: ${credentials.username}`,
        `- Application password: ${credentials.password}`,
        `- Server name to use in the config: ${credentials.serverName}`,
        '',
    ];
    if (id === 'claude-code') {
        return [
            ...details,
            'Please run this command in my terminal to add the server:',
            '',
            '```sh',
            config,
            '```',
            '',
            'Then run "claude mcp list" and tell me whether the server shows as connected. If it does not, restart Claude Code and check again.',
        ].join('\n');
    }
    if (id === 'other') {
        return [
            ...details,
            'This is a remote MCP server using the Streamable HTTP transport with HTTP Basic authentication. Please add it to this client\'s MCP configuration. Most clients accept this JSON format:',
            '',
            '```json',
            config,
            '```',
            '',
            'If this client uses a different configuration format, convert it, keeping the URL and the Authorization header exactly as they are. Then restart the client if needed.',
        ].join('\n');
    }
    const node = client.requiresNode
        ? ['This configuration runs the mcp-remote helper through npx, which needs Node.js 18 or newer. Before editing anything, run "node -v" and tell me if Node.js is missing or older than 18.', '']
        : [];
    return [
        ...details,
        ...node,
        'Please:',
        `1. Open or create the configuration file at ${client.configPath.unix} on macOS or Linux, or ${client.configPath.windows} on Windows.`,
        `2. Add this server to it, keeping any servers already there:`,
        '',
        `\`\`\`${client.format}`,
        config,
        '```',
        '',
        '3. Save the file.',
        `4. Restart ${client.label}.`,
    ].join('\n');
};

/** A prompt that asks the connected client which tools it found. */
export const TEST_PROMPT = 'List the tools from the Media Library Tools MCP server (their names start with "tsmlt-"). Then call tsmlt-search-media with per_page set to 1 and tell me whether it worked.';
