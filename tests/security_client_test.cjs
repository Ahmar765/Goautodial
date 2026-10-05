const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const listeners = {};
class XHR {
  open() {}
  send() {}
  setRequestHeader(name, value) { this.header = [name, value]; }
}
class Form {
  constructor() { this.fields = []; }
  querySelector() { return this.fields.find(field => field.name === '_csrf'); }
  appendChild(field) { this.fields.push(field); }
  submit() { this.submitted = true; }
}
let fetched;
const document = {
  currentScript: { getAttribute: () => 'test-csrf-token' }, forms: [],
  addEventListener: (name, callback) => { listeners[name] = callback; },
  createElement: tag => tag === 'form' ? new Form() : {}, body: { appendChild() {} }
};
const window = { fetch: request => { fetched = request; return Promise.resolve(); } };
const context = { window, document, location: { href: 'https://crm.example.com/login.php', origin: 'https://crm.example.com' },
  XMLHttpRequest: XHR, HTMLFormElement: Form, URL, Request, Headers };
vm.runInNewContext(fs.readFileSync('js/security.js', 'utf8'), context);
let request = new XHR(); request.open('POST', '/php/AddUser.php'); request.send('data');
assert.deepEqual(request.header, ['X-CSRF-Token', 'test-csrf-token']);
request = new XHR(); request.open('GET', '/php/ViewUserInfo.php'); request.send(); assert.equal(request.header, undefined);
request = new XHR(); request.open('POST', 'https://other.example.com'); request.send(); assert.equal(request.header, undefined);
window.fetch('https://crm.example.com/php/AgentAPI.php', { method: 'POST', headers: { 'X-Custom': 'kept' } });
assert.equal(fetched.headers.get('X-CSRF-Token'), 'test-csrf-token');
assert.equal(fetched.headers.get('X-Custom'), 'kept');
window.fetch('https://other.example.com/', { method: 'POST' });
assert.equal(fetched.headers.get('X-CSRF-Token'), null);
window.fetch('https://crm.example.com/', { method: 'GET' });
assert.equal(fetched.headers.get('X-CSRF-Token'), null);
let form = new Form(); form.method = 'post'; form.action = '/login.php'; form.submit();
assert.equal(form.fields[0].value, 'test-csrf-token'); assert.equal(form.submitted, true);
form.submit(); assert.equal(form.fields.length, 1);
form = new Form(); form.method = 'post'; form.action = 'https://other.example.com/'; form.submit();
assert.equal(form.fields.length, 0);
form = new Form(); form.method = 'post'; form.action = '/login.php';
listeners.submit({ target: form }); assert.equal(form.fields[0].value, 'test-csrf-token');
let prevented = false;
listeners.click({ target: { closest: () => ({ href: 'https://crm.example.com/logout.php' }) },
  preventDefault: () => { prevented = true; }, stopImmediatePropagation() {} });
assert.equal(prevented, true);
console.log('Browser CSRF integration checks passed.');
