import { useSearchParams } from "expo-router/build/hooks";
import React from "react";
import { Platform, ScrollView } from "react-native";
import { View } from "react-native-web";
import { WebView } from "react-native-webview";
import { useLocalSearchParams } from "expo-router";

export default function CertificateModify() {
  const { atividade_id } = useLocalSearchParams<{ atividade_id?: string }>();

  const pageUrlBase = "http://localhost/SICAD/editor/index.html";

  
  const pageUrl = atividade_id
    ? `${pageUrlBase}?atividade_id=${atividade_id}`
    : pageUrlBase;
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