import Script from 'next/script';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'API Reference · A1TechFlow SMS',
  description:
    'REST API for sending and receiving SMS through A1TechFlow SMS. Interactive OpenAPI reference with code samples in curl, JS, PHP and Python.',
};

export default function DocsPage() {
  const configuration = {
    theme: 'default',
    layout: 'modern',
    hideDownloadButton: false,
    defaultHttpClient: { targetKey: 'shell', clientKey: 'curl' },
    metaData: { title: 'A1TechFlow SMS API Reference' },
    searchHotKey: 'k',
  };

  return (
    <div id="scalar-container" style={{ minHeight: '100vh' }}>
      <script
        id="api-reference"
        data-url="/openapi.yaml"
        data-configuration={JSON.stringify(configuration)}
      />
      <Script
        src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"
        strategy="afterInteractive"
      />
    </div>
  );
}
