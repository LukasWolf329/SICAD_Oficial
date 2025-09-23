import "../../../../style/global.css";

import React, { useState, useRef } from 'react';
import { 
  Text, 
  View, 
  Image, 
  ScrollView, 
  Pressable, 
  TextInput, 
  Dimensions,
  TouchableOpacity,
  Alert,
  Linking
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Link, router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';


import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { CertifyBox, InfoBox, PeopleBox } from "@/components/InfoBox";

// Componentes para o editor de certificados
const CertificateEditor = () => {
  const [selectedTool, setSelectedTool] = useState('text');
  const [fontFamily, setFontFamily] = useState('Arial');
  const [fontSize, setFontSize] = useState(24);
  const [fontColor, setFontColor] = useState('#000000');
  const [textAlign, setTextAlign] = useState('left');
  const [isBold, setIsBold] = useState(false);
  const [isItalic, setIsItalic] = useState(false);
  const [isUnderline, setIsUnderline] = useState(false);
  const [shapeColor, setShapeColor] = useState('#007bff');
  const [noFill, setNoFill] = useState(false);

  const handleAddText = () => {
    Alert.alert('Adicionar Texto', 'Funcionalidade de adicionar texto será implementada aqui');
    // Em uma implementação real, isso adicionaria um componente de texto ao canvas
  };

  const handleAddShape = (shapeType: string) => {
    Alert.alert('Adicionar Forma', `Forma ${shapeType} será adicionada`);
    // Em uma implementação real, isso adicionaria uma forma ao canvas
  };

  const handleAddLine = (lineType: string) => {
    Alert.alert('Adicionar Linha', `Linha ${lineType} será adicionada`);
    // Em uma implementação real, isso adicionaria uma linha ao canvas
  };

  const handleInsertTag = (tag: string) => {
    Alert.alert('Inserir Tag', `Tag ${tag} será inserida`);
    // Em uma implementação real, isso inseriria uma tag no texto selecionado
  };

  const handleDownload = () => {
    Alert.alert('Download', 'Certificado será baixado');
    // Em uma implementação real, isso geraria e baixaria a imagem do certificado
  };

  const handleSetTemplate = (templateId: number) => {
    Alert.alert('Template', `Template ${templateId} será aplicado`);
    // Em uma implementação real, isso carregaria um template de fundo
  };

  return (
    <View className="flex-1 p-4">
      <View className="flex-row mb-4">
        {/* Painel lateral de formas */}
        <View className="w-1/4 pr-4 border-r border-gray-300">
          <Text className="text-lg font-bold mb-2">Formas</Text>
          <TouchableOpacity 
            className="bg-blue-100 p-2 rounded mb-2"
            onPress={() => handleAddShape('rect')}
          >
            <Text>Quadrado</Text>
          </TouchableOpacity>
          <TouchableOpacity 
            className="bg-blue-100 p-2 rounded mb-2"
            onPress={() => handleAddShape('circle')}
          >
            <Text>Círculo</Text>
          </TouchableOpacity>
          <TouchableOpacity 
            className="bg-blue-100 p-2 rounded mb-4"
            onPress={() => handleAddShape('triangle')}
          >
            <Text>Triângulo</Text>
          </TouchableOpacity>

          <Text className="text-lg font-bold mb-2">Linhas</Text>
          <TouchableOpacity 
            className="bg-blue-100 p-2 rounded mb-2"
            onPress={() => handleAddLine('normal')}
          >
            <Text>Linha</Text>
          </TouchableOpacity>
          <TouchableOpacity 
            className="bg-blue-100 p-2 rounded mb-2"
            onPress={() => handleAddLine('dashed')}
          >
            <Text>Linha Tracejada</Text>
          </TouchableOpacity>
          <TouchableOpacity 
            className="bg-blue-100 p-2 rounded mb-4"
            onPress={() => handleAddLine('arrow')}
          >
            <Text>Seta</Text>
          </TouchableOpacity>

          <Text className="text-lg font-bold mb-2">Estilo</Text>
          <View className="mb-2">
            <Text>Cor:</Text>
            <View className="flex-row items-center">
              <View 
                style={{ backgroundColor: shapeColor, width: 20, height: 20, borderWidth: 1, borderColor: '#ccc' }}
                className="mr-2"
              />
              <TextInput
                value={shapeColor}
                onChangeText={setShapeColor}
                className="border border-gray-300 p-1 flex-1"
              />
            </View>
          </View>
          <View className="flex-row items-center mb-4">
            <Text>Sem preenchimento:</Text>
            <Pressable 
              onPress={() => setNoFill(!noFill)}
              className={`w-6 h-6 border border-gray-400 ml-2 ${noFill ? 'bg-blue-500' : 'bg-white'}`}
            />
          </View>

          <Text className="text-lg font-bold mb-2">Tags</Text>
          <View className="border border-gray-300 rounded">
            <Picker
              selectedValue=""
              onValueChange={(itemValue: string) => handleInsertTag(itemValue)}
            >
              <Picker.Item label="-- Inserir Tag --" value="" />
              <Picker.Item label="Nome do Participante" value="{{NOME}}" />
              <Picker.Item label="Nome da Atividade" value="{{ATIVIDADE}}" />
              <Picker.Item label="Assinatura" value="{{ASSINATURA}}" />
            </Picker>
          </View>
        </View>

        {/* Área principal do editor */}
        <View className="flex-1 ml-4">
          {/* Barra de ferramentas */}
          <View className="flex-row flex-wrap mb-4">
            <TouchableOpacity 
              className="bg-blue-500 p-2 rounded mr-2 mb-2"
              onPress={handleAddText}
            >
              <Text className="text-white">Adicionar Texto</Text>
            </TouchableOpacity>

            <View className="border border-gray-300 rounded p-1 mr-2 mb-2">
              <Picker
                selectedValue={fontFamily}
                onValueChange={setFontFamily}
                style={{ width: 150 }}
              >
                <Picker.Item label="Arial" value="Arial" />
                <Picker.Item label="Times New Roman" value="Times New Roman" />
                <Picker.Item label="Courier New" value="Courier New" />
                <Picker.Item label="Verdana" value="Verdana" />
                <Picker.Item label="Georgia" value="Georgia" />
              </Picker>
            </View>

            <View className="flex-row items-center mr-2 mb-2">
              <Text>Cor:</Text>
              <View 
                style={{ backgroundColor: fontColor, width: 20, height: 20, borderWidth: 1, borderColor: '#ccc' }}
                className="mx-2"
              />
              <TextInput
                value={fontColor}
                onChangeText={setFontColor}
                className="border border-gray-300 p-1 w-20"
              />
            </View>

            <View className="flex-row items-center mr-2 mb-2">
              <Text>Tamanho:</Text>
              <TextInput
                value={String(fontSize)}
                onChangeText={(text) => setFontSize(Number(text))}
                keyboardType="numeric"
                className="border border-gray-300 p-1 w-16 ml-2"
              />
            </View>

            <TouchableOpacity 
              className={`p-2 rounded mr-2 mb-2 ${isBold ? 'bg-blue-500' : 'bg-gray-200'}`}
              onPress={() => setIsBold(!isBold)}
            >
              <Text className={isBold ? 'text-white' : 'text-black'}>Negrito</Text>
            </TouchableOpacity>

            <TouchableOpacity 
              className={`p-2 rounded mr-2 mb-2 ${isItalic ? 'bg-blue-500' : 'bg-gray-200'}`}
              onPress={() => setIsItalic(!isItalic)}
            >
              <Text className={isItalic ? 'text-white' : 'text-black'}>Itálico</Text>
            </TouchableOpacity>

            <TouchableOpacity 
              className={`p-2 rounded mr-2 mb-2 ${isUnderline ? 'bg-blue-500' : 'bg-gray-200'}`}
              onPress={() => setIsUnderline(!isUnderline)}
            >
              <Text className={isUnderline ? 'text-white' : 'text-black'}>Sublinhar</Text>
            </TouchableOpacity>

            <View className="border border-gray-300 rounded p-1 mr-2 mb-2">
              <Picker
                selectedValue={textAlign}
                onValueChange={setTextAlign}
                style={{ width: 120 }}
              >
                <Picker.Item label="Esquerda" value="left" />
                <Picker.Item label="Centro" value="center" />
                <Picker.Item label="Direita" value="right" />
              </Picker>
            </View>

            <TouchableOpacity 
              className="bg-green-500 p-2 rounded mr-2 mb-2"
              onPress={handleDownload}
            >
              <Text className="text-white">Baixar</Text>
            </TouchableOpacity>
          </View>

          {/* Área do canvas (simulada) */}
          <View className="border border-gray-300 bg-white h-96 mb-4 items-center justify-center">
            <Text className="text-gray-500">Área de Visualização do Certificado</Text>
            <Text className="text-gray-400 mt-2">(Em uma implementação real, isto seria um canvas interativo)</Text>
          </View>

          {/* Galeria de templates */}
          <Text className="text-lg font-bold mb-2">Templates</Text>
          <ScrollView horizontal className="mb-4">
            {[1, 2, 3, 4].map((id) => (
              <TouchableOpacity 
                key={id}
                className="mr-4"
                onPress={() => handleSetTemplate(id)}
              >
                <View className="w-32 h-40 border border-gray-300 rounded items-center justify-center">
                  <Text>Template {id}</Text>
                </View>
              </TouchableOpacity>
            ))}
          </ScrollView>
        </View>
      </View>
    </View>
  );
};

// Componente Picker para React Native
import { Picker } from '@react-native-picker/picker';

export default function Certificate() {
  return (
    <SafeAreaView className="flex-1 bg-slate-50 dark:bg-black">
      <Mainframe name="SICAD - Evento de Teste " photoUrl="evento.png" link="www.evento.com">
        <CertificateEditor />
      </Mainframe>
    </SafeAreaView>
  );
}