import Ionicons from '@expo/vector-icons/build/Ionicons';
import { Redirect, router } from 'expo-router';
import React, { useState } from 'react';
import { Dimensions, Image, Pressable, Text, TextInput, View } from 'react-native';
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

export function SideBar(){
  return (
    <View
      className='p-4 w-3/12'
      style={{position: 'absolute', left: 0, top: 100, zIndex: 10}}
    >

      <Text className='text-2xl color-white font-bold mb-4'>Gestão</Text>

      <Pressable className='flex-row items-center mb-4 color-slate-500 hover:color-white'>
        <Text className="text-2xl ml-1 color-slate-400 hover:color-white "><Ionicons name="home" size={24} className='mr-2'/>Inicio</Text>
      </Pressable>
      <Pressable className='flex-row items-center mb-4 color-slate-500 hover:color-white'>
        <Text className="text-2xl ml-1 color-slate-400 hover:color-white "><Ionicons name="ticket" size={24} className='mr-2'/>Ingresso</Text>
      </Pressable>
      <Pressable
        className='flex-row items-center mb-4 color-slate-500 hover:color-white'
        onPress={() => router.push('./certificates')}
      >
        <Text className="text-2xl ml-1 color-slate-400 hover:color-white ">
          <Ionicons name="medal-sharp" size={24} className='mr-2'/>Certificados
        </Text>
      </Pressable>

    </View>
  );
}

export function Mainframe(){
  const screenHeight = Dimensions.get('window').height;
  return (
    <View
      className='w-9/12 bg-white dark:bg-gray-950 p-4 self-end'
      style={{ height: screenHeight - 150, alignSelf: 'flex-end', borderBottomLeftRadius: 20 }}

    >
        
    </View>
  );
}
