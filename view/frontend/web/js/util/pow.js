define([], function () {
    return {
        debugMode: false,
        intervalRef: null,
        counter: 0,
        solution: null,

        getSolution: function () {
            return window?.airwallex_pow?.solution;
        },

        // callback - execute when solution is found
        solvePOW: async function () {
            this.debug('Get solve');
            if (typeof window.airwallex_pow === 'undefined') {
                // Assume POW check is disabled
                this.debug('Assuming disabled');
                return '';
            }

            if (this.getSolution()) {
                this.debug('Solution exists');
                return this.getSolution();
            }

            const { prefix, difficulty, nonce, separator } = window.airwallex_pow;
            const targetHashStart = '0'.repeat(difficulty);
            const solutionStart = prefix + separator + nonce + separator;

            this.debug('Starting solve');
            while (true) {
                const solution = solutionStart + this.counter;
                this.counter++;
                if (this.counter % 100 === 0) {
                    this.debug('Tried ' + this.counter + ' hashes');
                }
                const hash = await this.sha256(await this.sha256(solution));
                if (hash.startsWith(targetHashStart)) {
                    this.debug('Found solution: ' + solution + ' hash: ' + hash);
                    window.airwallex_pow.solution = solution;
                    return solution;
                }
            }
        },

        sha256: function (data) {
            const utf8 = new TextEncoder().encode(data);
            return crypto.subtle.digest('SHA-256', utf8).then((hashBuffer) => {
                const hashArray = Array.from(new Uint8Array(hashBuffer));

                return hashArray
                    .map((bytes) => bytes.toString(16).padStart(2, '0'))
                    .join('')
                    .toString();
            });
        },

        debug: function (msg) {
            if (this.debugMode) {
                console.log(msg);
            }
        }
    };
});
