import React from 'react';
import { ScrollView, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { NavBar, SideBar } from '../../../../components/NavBar';

import { Platform } from "react-native";


export default function Profile() {
  return (
    <ScrollView className="flex-1 bg-white dark:bg-black">
      <NavBar />
      <SideBar />

      <View className="flex-1 mx-4 my-2 border rounded-lg overflow-hidden">
        <WebView
          originWhitelist={['*']}
          source={{ html: `
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
              <meta charset="UTF-8">
              <title>Editor de Certificados</title>
              <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
              <style>
                body { margin:0; padding:0; }
                #c { border:1px solid #ccc; }
              </style>
            </head>
            <body>
              <canvas id="c" width="800" height="600"></canvas>
              <script>
                const canvas = new fabric.Canvas('c');
                // Aqui você injeta todo seu código de edição: texto, formas, tags, templates...
              </script>
            </body>
            </html>
          ` }}
          style={{ height: 650 }}
        />
      </View>
    </ScrollView>
  );
}
