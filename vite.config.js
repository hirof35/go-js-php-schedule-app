import { defineConfig } from 'vite';

export default defineConfig({
  base: './', // サブディレクトリ配下でのアセット読み込みパスの相対化
  build: {
    outDir: '.', // Apacheから直接見えるようにルートに直接コンパイルを出力
    assetsDir: 'dist-assets', // 既存ファイルと衝突しないアセット退避フォルダ
    rollupOptions: {
      input: {
        main: './index.html' // エントリーポイント
      }
    }
  }
});