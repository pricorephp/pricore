# MCP Server

Pricore includes a [Model Context Protocol](https://modelcontextprotocol.io) (MCP) server, so AI assistants such as Claude, Cursor and VS Code can look up your packages, versions and vulnerabilities, and handle routine registry tasks for you.

The server is available at `https://pricore.yourcompany.com/mcp`. Clients sign in with your Pricore account through OAuth, so there is no token to create or paste.

## Connecting a Client

### Claude Code

```bash
claude mcp add --transport http pricore https://pricore.yourcompany.com/mcp
```

Run `/mcp` in Claude Code and choose **Authenticate** to sign in.

### Claude Desktop and claude.ai

Go to **Settings** > **Connectors**, choose **Add custom connector** and enter the `/mcp` URL. Connectors are reached from Anthropic's servers, so Pricore must be accessible from the internet.

### Cursor and VS Code

Add Pricore as a remote MCP server using the `/mcp` URL. For example, in `.cursor/mcp.json` or `.vscode/mcp.json`:

```json
{
  "servers": {
    "pricore": {
      "type": "http",
      "url": "https://pricore.yourcompany.com/mcp"
    }
  }
}
```

### Authorizing

When a client connects for the first time, your browser opens Pricore. Sign in if needed, review the request and choose **Authorize**. The client then receives a token that it refreshes automatically.

## Tools

| Tool | Description | Required role |
|------|-------------|---------------|
| `list-organizations` | Organizations you belong to, with your role and Composer repository URL | Any |
| `search-packages` | Search packages by name or description | Member |
| `get-package` | Versions, requirements and advisories of a package version | Member |
| `list-vulnerabilities` | Security advisories affecting the latest stable version and dev branches of each package | Member |
| `list-repositories` | Connected repositories and their sync status | Member |
| `sync-repository` | Queue a repository sync | Admin or owner |
| `add-repository` | Connect a repository and start its first sync | Admin or owner |
| `create-access-token` | Create an organization Composer token | Admin or owner |

## Permissions

An MCP client acts as you. It can reach every organization you belong to, and each tool checks your role in that organization exactly like the web interface does. Deleting packages or repositories, managing members and changing organization settings are not available through MCP.

Tokens created with `create-access-token` are returned to the assistant once, so treat the conversation as you would any place a secret is shown.

## Revoking Access

Go to **Settings** > **Connected Apps** to see the clients you have authorized and revoke any of them. A revoked client stops working immediately and has to be authorized again to reconnect.

## Self-Hosting

The MCP server is enabled by default. See [MCP Server configuration](/getting-started/configuration#mcp-server) to disable it, restrict redirect URIs or provide your own OAuth keys.
