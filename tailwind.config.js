/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './missing-media-restorer.php',
    './assets/js/*.js',
    './pro/**/*.php',
    './pro/assets/js/*.js',
  ],
  theme: {
    extend: {
      colors: {
        'mmr-primary': '#4f46e5',
        'mmr-secondary': '#7c3aed',
      }
    },
  },
  plugins: [],
}
