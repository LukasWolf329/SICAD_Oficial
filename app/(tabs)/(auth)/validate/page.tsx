
import "../../../../style/global.css";
import axios from "axios";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { Link, useRouter } from "expo-router";
import React from 'react';
import { Image, Pressable, SafeAreaView, Text, TextInput, View } from 'react-native';
import NiceAlert from "../../../../components/NiceAlert/NiceAlert";
import { authCheck } from "@/app/authCheck/authCheck";
import { SafeAreaProvider } from "react-native-safe-area-context";

export default function Login() {

  //authCheck(); 

  const [codigo_certificado, setCodigo_certificado] = React.useState('');
  const [alertVisible, setAlertVisible] = React.useState(false);
  const [alertTitle, setAlertTitle] = React.useState('');
  const [alertMessage, setAlertMessage] = React.useState('');
  
  const router = useRouter();

  async function handleSignIn() {
    // Oii Lukinha -- redefinir o codigo para verificação do codigo aqui B    
  }

  return (
    <View className="flex-1 flex-row bg-white dark:bg-[#121212]">
      <View id="aside" className=" w-5/12"> 
        <Image source={require('../../../../assets/images/side-view-login-cadastro.png')} style={{ width: '100%' }} className=" mobile:h-0 mobile:w-0 mobile:hidden"/>
      </View>
      <View className="flex-1 items-center justify-center">
        <View>
          <Image source={require('../../../../assets/images/logo-composta.png')} className="mb-4" />
          <Text className="text-6xl dark:color-white">Autenticidade de <br></br>Documento</Text>
          <form action="" className="flex flex-col gap-1 mt-4">
            <View>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput value={codigo_certificado} placeholder="Codigo" onChangeText={setCodigo_certificado} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4 color-slate-400"/>
                </SafeAreaView>
              </SafeAreaProvider>
            </View>
            <Pressable onPress={handleSignIn} className="w-full h-12 rounded-xl bg-green-600 text-2xl font-bold mt-4 color-white justify-center items-center">Verificar</Pressable>
          </form>
        </View>
      </View>
      <NiceAlert
      visible={alertVisible}
      title={alertTitle}
      message={alertMessage}
      onClose={() => setAlertVisible(false)}
    />
    </View>
  );
}
