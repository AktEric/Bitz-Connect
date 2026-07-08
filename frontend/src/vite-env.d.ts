/// <reference types="vite/client" />

// Allow CSS imports as side-effect modules
declare module '*.css' {
  const content: string
  export default content
}
