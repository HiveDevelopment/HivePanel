<p align="center">
  <a href="https://hivepanel.dev">
    <img alt="HivePanel" src="https://hivepanel.dev/assets/imgs/HivePanelLogo.png" height="90">
  </a>
</p>

<p align="center">
  <strong>Modern, open-source game server management.</strong>
</p>

<p align="center">
  <a href="https://github.com/HiveDevelopment/HivePanel/actions">
    <img src="https://img.shields.io/github/actions/workflow/status/HiveDevelopment/HivePanel/release.yml?label=Build&style=for-the-badge" alt="Build Status">
  </a>
  <a href="https://github.com/HiveDevelopment/HivePanel/releases">
    <img src="https://img.shields.io/github/v/release/HiveDevelopment/HivePanel?style=for-the-badge" alt="Latest Release">
  </a>
  <a href="https://github.com/HiveDevelopment/HivePanel/graphs/contributors">
    <img src="https://img.shields.io/github/contributors/HiveDevelopment/HivePanel?style=for-the-badge" alt="Contributors">
  </a>
  <a href="https://hivepanel.dev/r/discord">
    <img src="https://img.shields.io/discord/1522542806831206462?label=Discord&logo=Discord&logoColor=white&style=for-the-badge" alt="Discord">
  </a>
</p>

# HivePanel

HivePanel is a free, open-source game server management platform built with **Laravel, Vue, and Go**.

Designed to be fast, modern, and developer-friendly, HivePanel provides a clean interface for deploying and managing game servers while running workloads through isolated containerised environments.

From a single home server to distributed hosting infrastructure, HivePanel is designed to make server management simple without sacrificing the tools administrators need.

## The HivePanel Ecosystem

HivePanel is built around several components that work together:

### Panel

The central web interface and API.

The Panel handles users, authentication, permissions, servers, configuration, deployments, nodes, updates, and communication with Workers.

### Workers

Workers run alongside your server infrastructure and handle the workloads managed by HivePanel.

They communicate with the Panel and manage server processes, files, networking, backups, resource usage, and container runtimes.

### Combs

Combs describe how workloads are installed, configured, and operated.

Rather than tying HivePanel to a fixed list of games, Combs allow support for games, applications, bots, and other workloads to be extended independently.

### Registry

The HivePanel Registry provides a central place for discovering and distributing supported Combs and other ecosystem resources.

Together, these components allow HivePanel to scale from a simple installation to infrastructure spanning multiple Workers and locations.

## Features

HivePanel is being built around a modern server-management experience, including:

- Multi-node server management
- Isolated Docker-based workloads
- Real-time server console and resource statistics
- File manager and file editor
- Backups
- Scheduled tasks
- Database management
- Network and allocation management
- Startup configuration
- Server importing and migration tools
- User and subuser permissions
- Administrative roles and permissions
- OpenID Connect authentication
- Managed panel updates
- Extensible Comb-based workload definitions
- Responsive modern interface
- API-driven architecture

## Installation

For production installations, use the official HivePanel installer.

```bash
curl -fsSL https://get.hivepanel.dev | sudo bash
```

The installer handles the required host configuration and deploys HivePanel using its supported containerised production environment.

For installation requirements, alternative platforms, development environments, and advanced deployment options, see the documentation.

> HivePanel is under active development. Review the release notes and documentation before deploying updates to production environments.

## Architecture

A typical HivePanel deployment looks like:

```text
                       ┌─────────────────────┐
                       │      HivePanel      │
                       │    Panel + API      │
                       └──────────┬──────────┘
                                  │
                    ┌─────────────┼─────────────┐
                    │             │             │
                    ▼             ▼             ▼
              ┌──────────┐  ┌──────────┐  ┌──────────┐
              │ Worker 01│  │ Worker 02│  │ Worker 03│
              └────┬─────┘  └────┬─────┘  └────┬─────┘
                   │              │              │
                   ▼              ▼              ▼
              Containers     Containers     Containers
```

The Panel provides the control plane while Workers perform workload operations on individual hosts.

This separation allows additional Workers to be added as your infrastructure grows.

## Supported Workloads

HivePanel uses **Combs** to define supported workloads.

This allows HivePanel to support more than a hard-coded collection of game servers.

Examples include:

- Minecraft servers
- SteamCMD-based game servers
- Discord bots
- Node.js applications
- Other containerised workloads

Additional games and applications can be provided through new Combs without requiring them to be built directly into the core Panel.

## Security

HivePanel is designed around separating the web application from privileged host operations.

Production installations use isolated containers, while Workers handle server workloads independently from the Panel.

Administrative access can be controlled using role-based permissions, while individual servers support their own subuser permission model.

If you discover a security vulnerability, please do not disclose it through a public GitHub issue.

Please follow the security reporting process documented by the HivePanel project.

## Documentation

- [HivePanel Website](https://hivepanel.dev)
- [Documentation](https://docs.hivepanel.dev)
- [GitHub](https://github.com/HiveDevelopment/HivePanel)
- [Releases](https://github.com/HiveDevelopment/HivePanel/releases)
- [Discord](https://hivepanel.dev/r/discord)

## Development

HivePanel is open source and contributions are welcome.

To work on HivePanel locally, clone the repository:

```bash
git clone https://github.com/HiveDevelopment/HivePanel.git
cd HivePanel
```

Production installations should use the official installer rather than deploying directly from a Git clone.

See the development documentation for information about setting up the Laravel application, frontend tooling, database, Redis, Workers, and other development dependencies.

## Contributing

Contributions of all sizes are welcome.

This can include:

- Bug fixes
- New features
- UI improvements
- Documentation
- Combs
- Performance improvements
- Testing
- Translations

Before starting a significant change, consider opening an issue or discussing the idea with the community first.

When submitting a pull request, please keep changes focused and provide enough information for the change to be reviewed and tested.

## Community

Need help, want to contribute, or just want to follow development?

Join the HivePanel community on [Discord](https://hivepanel.dev/r/discord).

You can also use GitHub Issues for reproducible bugs and feature requests.

## License

HivePanel is free and open-source software.

See the [LICENSE](./LICENSE) file for the full license terms.

---

<p align="center">
  <strong>Built by the HivePanel community.</strong>
</p>