import Ionicons from '@expo/vector-icons/build/Ionicons';
import { Redirect, router } from 'expo-router';
import React, { useState } from 'react';
import { Dimensions, Image, Pressable, Text, TextInput, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';

interface InputProps {  
  icon: string;
  counter?: string;
  name?: string;
}

export function InfoBox({icon, counter, name}: InputProps){
  return (
    <View style={{width: '30%' }} className='border-x border-y border-slate-400 flex-row justify-between items-center py-4 px-5 rounded-xl mt-4'>
      <View>
        <Ionicons name={icon} size={30} className='mr-2'/>
      </View>
      <View className='items-end'>
        <Text className='text-xl font-bold'>{counter}</Text>
        <Text className='text-base'>{name}</Text>
      </View>
    </View>
  );
}

export function PeopleBox({photo, name, email}:{photo?: string; name?:string; email?:string}){
  return (
    <View className='border border-slate-400 flex-row justify-between items-center py-2 px-5 rounded-xl mt-2'>
      <View className='flex-row items-center gap-2'>
        <Image source={require('../../assets/images/user.png')} style={{width:60, height:60}} className='rounded-full'/>
        <View>
          <Text className='text-xl font-semibold'>{name}</Text>
          <Text className='text-base'>{email}</Text>
        </View>
      </View>
    </View>
  );
}

export function CertifyBox({titulo, valor, modelo, att, status}:{titulo?: string; valor?: float; modelo?: string; att?: string; status?: string}){
  return (
    <View className='flex-row justify-between items-center border-b border-slate-300 p-2'>
      <Text className='w-min h-min text-nowrap justify-center items-center p-1 text-lg color-slate-500'>{titulo}</Text>
      {
        valor == 0 ?
          (
            <Pressable className='w-min h-min  bg-[#2192ff] rounded-lg justify-center items-center p-1 mt-2'>
              <Text className='color-white'>Gratuito</Text>
            </Pressable>
          )
        :
          (
            <Pressable className='w-min h-min rounded-lg justify-center items-center p-1'>
              <Text className='color-slate-500 text-lg'>{"R$" + valor}</Text>
            </Pressable>
          )
      }
      <Pressable className='flex-row w-min h-min  bg-[#FF6200] rounded-lg justify-center items-center p-1 mt-2'>
        <Ionicons name="warning-outline" size={16} color={'white'}/>
        <Text className='text-white text-nowrap'>Criar Modelo</Text>
      </Pressable>
      {/*pensar na implemnetalçao da criação do modelo e verificação*/}
      <Pressable className='flex-row w-min h-min  bg-[#2192ff] rounded-lg justify-center items-center p-1 mt-2'>
        <Ionicons name="chevron-down-outline" size={16} color={'white'}/>
        <Text className='text-nowrap text-white'>Todos os inscritos</Text>
      </Pressable>
      {/*pensar na implemnetalçao da criação do modelo e atribuição*/}
      <Pressable className='flex-row w-min h-min  bg-[#FF0004] opacity-50 rounded-lg justify-center items-center p-1 mt-2'>
        <Text className='color-white text-nowrap'>Não Publicado</Text>
      </Pressable>
      {/*booleano vindo do banco de dados*/}
      <View className='flex-row flex-wrap gap-1'>
        <Pressable className='flex-row w-min h-min border border-slate-400 rounded-lg justify-center items-center p-1 mt-2'>
          <Ionicons name="checkmark" size={16} className='color-slate-400'/>
          <Text className='color-slate-400'>Publicar</Text>
        </Pressable>
        <Pressable className='flex-row w-min h-min border border-slate-400 rounded-lg justify-center items-center p-1 mt-2'>
          <Ionicons name="paper-plane-outline" size={16} className='color-slate-400'/>
          <Text className='color-slate-400'>Envio</Text>
        </Pressable>
      </View>
    </View>

  );
}