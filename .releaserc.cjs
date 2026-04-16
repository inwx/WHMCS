const plugins = [
  '@semantic-release/commit-analyzer',
  '@semantic-release/release-notes-generator',
  [
    '@semantic-release/github',
    {
      assets: [
        { path: 'release.zip' },
        { path: 'checksum.txt' },
      ],
    },
  ],
];

if (process.env.WHMCSMP_PUBLISH === 'true') {
  plugins.push('@hexonet/semantic-release-whmcs');
}

module.exports = {
  tagFormat: '${version}',
  branches: [
    'master',
    { name: 'beta', prerelease: true },
  ],
  plugins,
};
