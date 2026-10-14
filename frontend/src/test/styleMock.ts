/** Identity proxy for CSS modules in tests: `styles.foo` → `'foo'`. */
const styles: Record<string, string> = new Proxy(
  {},
  {
    get: (_target, key) => (key === '__esModule' ? false : String(key)),
  },
);

export default styles;
