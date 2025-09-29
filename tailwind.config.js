/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./templates/**/*.html.twig",
    "./assets/**/*.js",
    "./public/**/*.html",
  ],
  theme: {
    extend: {
      colors: {
        primary: '#1e40af', // blue-800
        secondary: '#059669', // emerald-600
        gembira: {
          primary: '#1e40af',
          secondary: '#059669',
        }
      },
      fontFamily: {
        'sans': ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'Noto Sans', 'sans-serif'],
      }
    },
  },
  plugins: [],
}