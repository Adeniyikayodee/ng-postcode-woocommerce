// Run through tests/browser/run.sh, which starts the store these tests need.
module.exports = {
	testDir: 'tests/browser',
	timeout: 90000,
	workers: 1,
	retries: 0,
	use: { baseURL: process.env.BASE_URL, trace: 'retain-on-failure' },
};
