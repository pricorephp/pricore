import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Pricore',
  description: 'Open-source private Composer registry for teams',

  head: [
    ['link', { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/getting-started/' },
      { text: 'Self-Hosting', link: '/self-hosting/' },
      { text: 'API', link: '/api/' },
      { text: 'Releases', link: 'https://github.com/pricorephp/pricore/releases' },
      { text: 'GitHub', link: 'https://github.com/pricorephp/pricore' },
    ],

    sidebar: {
      '/': [
        {
          text: 'Getting Started',
          items: [
            { text: 'Installation', link: '/getting-started/' },
            { text: 'Configuration', link: '/getting-started/configuration' },
          ],
        },
        {
          text: 'Guide',
          items: [
            { text: 'Organizations', link: '/guide/organizations' },
            { text: 'Packages', link: '/guide/packages' },
            { text: 'Repositories', link: '/guide/repositories' },
            { text: 'Access Tokens', link: '/guide/tokens' },
          ],
        },
        {
          text: 'Self-Hosting',
          items: [
            { text: 'Overview', link: '/self-hosting/' },
            { text: 'Docker', link: '/self-hosting/docker' },
            { text: 'Requirements', link: '/self-hosting/requirements' },
          ],
        },
        {
          text: 'Reference',
          items: [{ text: 'API', link: '/api/' }],
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/pricorephp/pricore' },
    ],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/pricorephp/pricore/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Released under the Apache 2.0 License.',
      copyright: 'Copyright 2024-present Pricore Contributors',
    },
  },
})
