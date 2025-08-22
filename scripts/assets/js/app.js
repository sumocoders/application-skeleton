// CSS
import '@fortawesome/fontawesome-free/css/all.css'
import 'tom-select/dist/css/tom-select.default.css'
import 'tom-select/dist/css/tom-select.bootstrap5.css'
import 'flatpickr/dist/flatpickr.css'
import 'flatpickr/dist/themes/airbnb.css'

// Fix CSP Nonce for Turbo requests
// See: https://github.com/hotwired/turbo/issues/294#issuecomment-877842232
document.addEventListener('turbo:before-fetch-request', (event) => {
  event.detail.fetchOptions.headers['X-CSP-Nonce'] = document.querySelector('meta[name="csp-nonce"]').content
})

// JS
import 'bootstrap' // eslint-disable-line import/first
import './bootstrap.js' // eslint-disable-line import/first
