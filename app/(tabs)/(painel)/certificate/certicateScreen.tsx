import React from "react";
import { Platform, ScrollView } from "react-native";
import { View } from "react-native-web";
import { WebView } from "react-native-webview";

export default function CertificateModify() {
  const pageUrl = "http://127.0.0.1:5500/SICAD/app/(tabs)/(painel)/certificate/editor.html"; // arquivo na pasta public

  return (
    <View>
      {Platform.OS === "web" ? (
        <iframe
        src={pageUrl}
        style={{
          width: "100%",
          height: "90vh",
          border: "none",
          background: "white",
        }}
        />
      ) : (
        <WebView
        originWhitelist={["*"]}
        source={{ uri: pageUrl }}
        style={{ flex: 1 }}
        />
      )}      
     </View>
  );
}