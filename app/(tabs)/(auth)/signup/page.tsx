import { navigate } from "expo-router/build/global-state/routing";
import "../../../../style/global.css";

import { Link } from "expo-router";
import React from 'react';
import { Image, Pressable, SafeAreaView, Text, TextInput, View } from 'react-native';

import { SafeAreaProvider } from "react-native-safe-area-context";

export default function Signup() {
    const [nome, setNome] = React.useState('');
    const [email, setEmail] = React.useState('');
    const [senha, setSenha] = React.useState('');
    const [c_senha, setCSenha] = React.useState('');

    function handleSignUp() {
        console.log('Criar conta com:', { nome, email, senha, c_senha });
        navigate('/(tabs)/(painel)/home/page'); // Redireciona para a página de perfil após o login
    }

    return (
        <View className="flex-1 flex-row bg-white dark:bg-[#121212] ">
            <View id="aside" className="w-5/12">
                <Image source={require('../../../../assets/images/side-view-login-cadastro.png')}/>
            </View>
            <View className="flex-1 items-center justify-center">
                <View>
                    <Image source={require('../../../../assets/images/logo-composta.png')} className="mb-4" />
                    <Text className="text-6xl dark:color-white">Crie sua conta</Text>
                    <Text className="text-2xl dark:color-white">Ja tem uma conta ? <Link href={'/'} className="text-2xl color-sky-500">clique aqui para fazer login </Link></Text>
                    <SafeAreaProvider>
                        <SafeAreaView>
                            <form action="" className="flex flex-col gap-1 mt-4">
                                <View>
                                    <Text className="text-2xl dark:color-white">Nome Completo</Text>                    
                                    <TextInput value={nome} onChangeText={setNome} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"/>                        
                                    <Text className="dark:color-white mt-0">Este nome sera utilizado em todos os documentos da plataforma</Text>
                                </View>
                                <View>
                                    <Text className="text-2xl dark:color-white">E-mail</Text>
                                    <TextInput value={email} onChangeText={setEmail} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"/>                    
                                </View>
                                <View>
                                    <Text className="text-2xl dark:color-white">Senha</Text>
                                    <TextInput secureTextEntry={true} value={senha} onChangeText={setSenha} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"/>
                                </View>
                                <View>
                                    <Text className="text-2xl dark:color-white">Confirmar Senha</Text>                    
                                    <TextInput secureTextEntry={true} value={c_senha} onChangeText={setCSenha} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"/>                        
                                </View>
                                <View className="w-full h-12 rounded-xl bg-slate-200 dark:bg-slate-800 text-2xl font-bold mt-4 items-center justify-center px-2">
                                    <Text className="dark:color-white">Ao criar uma conta, você concorda com os <Link href={'/'} className="dark:color-white underline">Termos de Serviço</Link> e a <Link href={'/'} className="dark:color-white underline">Política de Privacidade</Link> da plataforma.</Text>
                                </View>
                                <Pressable onPress={handleSignUp} className="w-full h-12 rounded-xl bg-green-600 text-2xl font-bold mt-4 color-white justify-center items-center">Entrar</Pressable>
                            </form>
                        </SafeAreaView>
                    </SafeAreaProvider>
                </View>
            </View>
        </View>
    );
}
