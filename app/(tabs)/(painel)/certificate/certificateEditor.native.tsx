// CertificateEditor.native.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

interface CertificateEditorProps {
  participantName: string;
  activityName: string;
}

const CertificateEditorNative: React.FC<CertificateEditorProps> = () => {
  return (
    <View style={styles.container}>
      <Text style={styles.text}>
        A edição de certificados está disponível somente na versão para desktop / navegador.
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    padding: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  text: {
    fontSize: 18,
    color: '#666',
  },
});

export default CertificateEditorNative;
