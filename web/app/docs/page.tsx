import Script from 'next/script';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'API Reference · PCIT SMS Gateway',
  description:
    'REST API for sending and receiving SMS through the PCIT SMS Gateway. Interactive OpenAPI reference with code samples in curl, JS, PHP and Python.',
};

export default function DocsPage() {
  const configuration = {
    theme: 'default',
    layout: 'modern',
    hideDownloadButton: false,
    defaultHttpClient: { targetKey: 'shell', clientKey: 'curl' },
    metaData: { title: 'PCIT SMS Gateway API Reference' },
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
