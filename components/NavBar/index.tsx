import Ionicons from '@expo/vector-icons/build/Ionicons';
import { Link, Redirect, router } from 'expo-router';
import React, { useState } from 'react';
import { Linking ,Dimensions, Image, Pressable, Text, TextInput, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';

interface InputProps {  
  label : string;
  placeholder?: string;
  note?: string;
}

export function NavBar(){
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
                <Pressable className="color-white flex-row">Nome do ususario <Ionicons name="caret-down-outline" size={24} className="color-white"/></Pressable>
            </View>
        </View>
    </View>
  );
}

export function SideBar({children}: {children?: React.ReactNode}){
  return (
    <View className='p-4 w-3/12' style={{position: 'absolute', left: 0, top: 100, zIndex: 10}}  >
      {children}
    </View>
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
            onPress={() => router.push(item.link)}
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
    <View className='bg-white dark:bg-gray-950 p-4 pb-20 self-end' style={{alignSelf: 'flex-end', borderBottomLeftRadius: 20 , width: '80%'}}>
      <View className='flex-row m-8 items-center'>
        <Image source={require('../../assets/images/user.png')} style={{width:80, height:80}} className='rounded-full'/>
        <View>
          <Text className='text-2xl mx-4 font-semibold'>{name}</Text>
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


