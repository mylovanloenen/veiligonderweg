import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// VITE_BASE wordt door de GitHub Pages-workflow op "/<repo>/" gezet.
export default defineConfig({
  plugins: [react()],
  base: process.env.VITE_BASE ?? '/',
})
