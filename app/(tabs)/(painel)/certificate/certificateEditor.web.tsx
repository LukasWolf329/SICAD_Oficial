// CertificateEditor.web.tsx
import React, { useCallback, useRef } from 'react';
import { View, StyleSheet, Dimensions } from 'react-native';
import { WebView } from 'react-native-webview';

interface CertificateEditorProps {
  participantName: string;
  activityName: string;
}

const CertificateEditorWeb: React.FC<CertificateEditorProps> = ({ participantName, activityName }) => {
  const webViewRef = useRef<any>(null);

  const htmlContent = `
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
      <style>
        body { margin: 0; padding: 0; }
        #toolbar { margin: 10px; display: flex; gap: 10px; flex-wrap: wrap; }
        canvas { border: 1px solid #ccc; }
      </style>
    </head>
    <body>
      <div id="toolbar">
        <button onclick="addText()">Adicionar Texto</button>
        <button onclick="downloadImage()">Baixar Certificado</button>
      </div>
      <canvas id="certCanvas" width="800" height="600"></canvas>
      <script>
        const canvas = new fabric.Canvas('certCanvas');

        function addText() {
          const text = new fabric.IText('${participantName}', { left: 100, top: 100, fontSize: 32, fill: '#000' });
          canvas.add(text);
          canvas.setActiveObject(text);
        }

        function downloadImage() {
          const dataURL = canvas.toDataURL({ format: 'png' });
          window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'download', data: dataURL }));
        }
      </script>
    </body>
    </html>
  `;

  const onMessage = useCallback((event: any) => {
    try {
      const msg = JSON.parse(event.nativeEvent.data);
      if (msg.type === 'download') {
        const imageData = msg.data;
        // Lógica para fazer algo com a imagem: abrir nova aba, baixar, mandar server, etc.
        console.log('Imagem do certificado:', imageData);
      }
    } catch (e) {
      console.warn('Erro ao receber mensagem da WebView:', e);
    }
  }, []);

  return (
    <View style={styles.webviewContainer}>
      <WebView
        ref={webViewRef}
        originWhitelist={['*']}
        source={{ html: htmlContent }}
        javaScriptEnabled={true}
        domStorageEnabled={true}
        style={{ flex: 1 }}
        onMessage={onMessage}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  webviewContainer: {
    flex: 1,
    width: '100%',
    height: Dimensions.get('window').height * 0.7, // ou ajustar conforme design
  },
});

export default CertificateEditorWeb;
