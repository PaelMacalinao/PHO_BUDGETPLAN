module.exports = {
  content: [
    "./**/*.php",
    "./**/*.html",
    "./assets/**/*.js",
    "!./node_modules/**"
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: "#f0faf3",
          100: "#d5f0dc",
          200: "#a8e0b9",
          300: "#6ec98a",
          400: "#3aad5e",
          500: "#1a7a3a",
          600: "#0b4d26",
          700: "#093f1f",
          800: "#073218",
          900: "#052611"
        },
        accent: {
          50: "#fef9e7",
          100: "#fdf0c4",
          200: "#fbe28a",
          300: "#f9d24f",
          400: "#f9ba15",
          500: "#e5a80e",
          600: "#bf8b09",
          700: "#8c6607",
          800: "#5f4504",
          900: "#332503"
        }
      }
    }
  },
  safelist: [
    "bg-blue-100",
    "text-blue-600",
    "text-blue-800",
    "bg-orange-100",
    "text-orange-600",
    "text-orange-800",
    "bg-purple-100",
    "text-purple-600",
    "text-purple-800"
  ]
};
