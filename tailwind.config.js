/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.html",  // Nếu có file HTML gốc
    "./*.php",   // File PHP gốc
    "./**/*.php", // File PHP trong các thư mục con
    // Thêm các đường dẫn khác nếu cần (ví dụ: thư mục 'src')
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}