
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

  const [email, setEmail] = React.useState('');
  const [senha, setSenha] = React.useState('');
  

  const [alertVisible, setAlertVisible] = React.useState(false);
  const [alertMessage, setAlertMessage] = React.useState("");
  const [alertTitle, setAlertTitle] = React.useState("Ocorreu um erro");

  const router = useRouter();

  function showError(message: string, title = "Ocorreu um erro") {
  setAlertTitle(title);
  setAlertMessage(message);
  setAlertVisible(true);
}

async function handleSignIn() {
    console.log('Login com:', { email, senha });
    // Redireciona para a página de perfil após o login
    if (!email || !senha) {
      showError('Por favor, preencha todos os campos');
      return;
    }
    
    try {
      const response = await axios.post("http://200.18.141.92/SICAD/login.php",
        {
          email: email,
          senha: senha,
        },
      );

      console.log("Resposta do Backend: ", response.data);
      if(response.data.success) {
        const token = response.data.token;
        const nome = response.data.usuario.nome;
        const id = response.data.usuario.id;
        await AsyncStorage.setItem("userToken", token);
        await AsyncStorage.setItem("userName", nome);
        await AsyncStorage.setItem("userId", id);
        router.push("/(tabs)/(painel)/home/page");
      }
      else {
        setAlertMessage(response.data.message);
        setAlertVisible(true);
        console.log(response.data.message || "Credenciais Invalidas");
      }
    } catch (error) {
      console.error("Erro na requisicao: ", error);
    }
  }
  return (
    <View className="flex-1 flex-row bg-white dark:bg-[#121212]">
      <View id="aside" className=" w-5/12"> 
        <Image source={require('../../../../assets/images/side-view-login-cadastro.png')} style={{ width: '100%' }} className=" mobile:h-0 mobile:w-0 mobile:hidden"/>
      </View>
      <View className="flex-1 items-center justify-center">
        <View>
          <Image source={require('../../../../assets/images/logo-composta.png')} className="mb-4" />
          <Text className="text-6xl dark:color-white">Acesse sua conta</Text>
          <Text className="text-2xl dark:color-white">Ainda não tem uma conta ? <Link href={'/(tabs)/(auth)/signup/page'} className="text-2xl color-sky-500">clique aqui para criar uma </Link></Text>
          <form action="" className="flex flex-col gap-1 mt-4">
            <View>
              <Text className="text-2xl dark:color-white">E-mail</Text>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput value={email} onChangeText={setEmail} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"/>
                </SafeAreaView>
              </SafeAreaProvider>
            </View>
            <View>
                <Text className="text-2xl dark:color-white">Senha</Text>
                <SafeAreaProvider>
                  <SafeAreaView>
                    <TextInput secureTextEntry={true} value={senha} onChangeText={setSenha} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"/>
                  </SafeAreaView>
                </SafeAreaProvider>                
            </View>
            <Pressable onPress={handleSignIn} className="w-full h-12 rounded-xl bg-green-600 text-2xl font-bold mt-4 color-white justify-center items-center">Entrar</Pressable>
          </form>
          <Link href={'/(tabs)/(auth)/signup/page'} className="dark:color-white underline text-xl mt-4">Esqueceu sua senha ?</Link>
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


//navigate('/(tabs)/(painel)/home/page');