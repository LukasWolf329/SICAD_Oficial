import Ionicons from '@expo/vector-icons/build/Ionicons';
import {router } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { Linking ,Dimensions, Image, Pressable, Text, View } from 'react-native';

interface InputProps {  
  label : string;
  placeholder?: string;
  note?: string;
}

export function NavBar(){ // esta dando errado nao sei onde esta o erro
  const [nomeUsuario, setnomeUsuario] = useState("");
  useEffect(() => {
    const fetchUsuario = async () => {
      try {
        const rest = await fetch("http://192.168.1.106/SICAD/get_usuario.php", {
          method:"GET",
          headers: {
            "Content-Type": "application/json",
          }, 
          credentials: "include",
        });
        const data = await rest.json();
        if(data.success) {
          setnomeUsuario(data.nome);
        }
        else {
          console.log("Usuario nao autenticado: ", data.message);
        }
      } catch (err) {
        console.error("Erro ao buscar o nome do usuario: ", err);
      }
    };

    fetchUsuario();
  }, []);

  return (
    <View className="flex-row justify-between items-center px-4 bg-[#059212]">
        <View className="p-2">
            <Image source={require('../../assets/images/logo-composta-branca.png')} />
        </View>
        <View className="flex-row items-center gap-6">
            <View>
                <Pressable className="color-white flex-row">Meus eventos <Ionicons name="caret-down-outline" size={24} className="color-white"/></Pressable>
            </View>
            <View>
                <Pressable className="color-white flex-row">Area do participante <Ionicons name="caret-down-outline" size={24} className="color-white"/></Pressable>
            </View>
            <View className="flex-row items-center gap-4">
                <Image source={require('../../assets/images/favicon.png')} style={{width: 40, height: 40}} className="rounded-full"/>
                <Pressable className="color-white flex-row">Nome do usuario<Ionicons name="caret-down-outline" size={24} className="color-white"/></Pressable>
            </View>
        </View>
    </View>
  );
}


export function SideBar({ children }: { children?: React.ReactNode }) {
  const screenWidth = Dimensions.get("window").width;
  const isSmall = screenWidth < 768;
  const [isOpen, setIsOpen] = React.useState(!isSmall);

  React.useEffect(() => {
    setIsOpen(!isSmall); // reabre sidebar em desktop
  }, [screenWidth]);

  return (
    <>
      {/* Botão Hamburger apenas em telas pequenas */}
      {isSmall && (
        <Pressable
          onPress={() => setIsOpen(!isOpen)}
          className="absolute top-4 left-4 z-20 p-2 bg-[#059212] rounded"
        >
          <Ionicons name={isOpen ? "close" : "menu"} size={28} color="white" />
        </Pressable>
      )}

      {/* Sidebar */}
      {isOpen && (
        <View
          className={`h-full p-4 ${
            isSmall
              ? "absolute left-0 top-0 w-[70%] z-10 shadow-lg"
              : "w-[250px]"
          }`}
        >
          {children}
        </View>
      )}
    </>
  );
}

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface SideBarCategoryItem {
  nome: string;
  icone: IoniconName;
  link: string;
}

interface SideBarCategoryProps {
  titulo: string;
  itens: SideBarCategoryItem[];
}

export function SideBarCategory({ titulo, itens }: SideBarCategoryProps) {
  return (
    <View>
      <Text className='text-2xl color-slate-600 dark:color-white font-bold mb-4'>{titulo}</Text>
      <View>
        {itens.map((item, idx) => (
          <Pressable
            key={idx}
            className='flex-row items-center mb-4 mx-4 color-slate-500 hover:color-white'
            onPress={() => router.push(item.link as any)}
          >
            <Text className="text-2xl ml-1 color-slate-400 hover:color-black dark:hover:color-white">
            <Ionicons name={item.icone} size={24} className='mr-2' />
              {item.nome}
            </Text>
          </Pressable>
        ))}
      </View>
    </View>
  );
}

export function Mainframe({children, name, photoUrl, link}: {children?: React.ReactNode; name: string; photoUrl?: string; link?: string}){
  const screenHeight = Dimensions.get('window').height;
  const screenWidth = Dimensions.get('window').width;
  return (
    <View className="bg-white dark:bg-[#242424] p-4 pb-20">
      <View className='flex-row m-8 items-center'>
        <Image source={require('../../assets/images/user.png')} style={{width:80, height:80}} className='rounded-full'/>
        <View>
          <Text className='text-2xl mx-4 font-semibold dark:color-[#e0e0e0]'>{name}</Text>
          {link != null &&(
            <Pressable onPress={() => Linking.openURL(`https://${link}`)}>
              <Text className='text-sky-500 mx-4 font-semibold'>{link}</Text>
            </Pressable>
          )}
        </View>
      </View>
      {children}
    </View>
  );
}


