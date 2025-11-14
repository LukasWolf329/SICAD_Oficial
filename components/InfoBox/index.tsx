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

export function InfoBox({ icon, counter, name }: InputProps) {
  const screenWidth = Dimensions.get('window').width;
  const isSmall = screenWidth < 600; // breakpoint simples

  return (
    <View
      className={`border border-slate-400 flex-row ${
        isSmall ? 'flex-col items-center w-full' : 'justify-between w-[30%]'
      } py-4 px-5 rounded-xl mt-4`}
    >
      <Ionicons name={icon} size={30} className="dark:color-[#e0e0e0] mb-2" />
      <View className={isSmall ? 'items-center' : 'items-end'}>
        <Text className="text-xl font-bold dark:color-[#e0e0e0]">{counter}</Text>
        {!isSmall && <Text className="text-base dark:color-[#e0e0e0]">{name}</Text>}
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
          <Text className='text-xl font-semibold dark:color-white'>{name}</Text>
          <Text className='text-base dark:color-white'>{email}</Text>
        </View>
      </View>
    </View>
  );
}

export function CertifyBox({
  titulo,
  valor,
  modelo,
  att,
  status,
}: {
  titulo?: string;
  valor?: number;
  modelo?: string;
  att?: string;
  status?: string;
}) {
  return (
    <View className="flex-row border-b border-slate-300">
      {/* TITULO */}
      <View className="w-3/12 p-2 justify-center">
        <Text className="text-lg color-slate-500">{titulo}</Text>
      </View>

      {/* VALOR */}
      <View className="w-1/12 p-2 justify-center items-center">
        {valor === 0 ? (
          <Pressable className="bg-[#2192ff] rounded-lg px-2 py-1">
            <Text className="text-white text-center">Gratuito</Text>
          </Pressable>
        ) : (
          <Text className="text-slate-500 text-center">{"R$" + valor}</Text>
        )}
      </View>

      {/* MODELO */}
      <View className="w-2/12 p-2 justify-center items-center">
        <Pressable 
          onPress={() => router.push("./certicateScreen")}
          className="flex-row bg-[#FF6200] rounded-lg px-2 py-1 justify-center items-center">
          <Ionicons name="warning-outline" size={16} color="white" />
          <Text className="text-white ml-1">Criar Modelo</Text>
        </Pressable>
      </View>

      {/* ATRIBUIÇÃO */}
      <View className="w-2/12 p-2 justify-center items-center">
        <Pressable className="flex-row bg-[#2192ff] rounded-lg px-2 py-1 justify-center items-center">
          <Ionicons name="chevron-down-outline" size={16} color="white" />
          <Text className="text-white ml-1">Todos os inscritos</Text>
        </Pressable>
      </View>

      {/* STATUS */}
      <View className="w-2/12 p-2 justify-center items-center">
        <Pressable className="bg-[#FF0004] opacity-50 rounded-lg px-2 py-1">
          <Text className="text-white text-center">{status}</Text>
        </Pressable>
      </View>

      {/* OPÇÕES */}
      <View className="w-2/12 p-2 flex-row gap-1 justify-center items-center">
        <Pressable className="flex-row border border-slate-400 rounded-lg px-2 py-1 justify-center items-center">
          <Ionicons name="checkmark" size={16} className="color-slate-400" />
          <Text className="color-slate-400 ml-1">Publicar</Text>
        </Pressable>
        <Pressable className="flex-row border border-slate-400 rounded-lg px-2 py-1 justify-center items-center">
          <Ionicons name="paper-plane-outline" size={16} className="color-slate-400" />
          <Text className="color-slate-400 ml-1">Envio</Text>
        </Pressable>
      </View>
    </View>
  );
}

export function ParticipantCertifyBox({
  participante,
  email,
  status
}: {
  participante?: string;
  email?: string;
  status?: number;
}) {

  const enviarCertificado = async () => {
    try {
      const response = await fetch("http://192.168.1.100/SICAD/enviar_certificado.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email }),
      });

      const data = await response.json();

      if (data.success) {
        console.log(`Certificado enviado para ${email}`);
      } else {
        console.log("Erro:", data.message || "Falha ao enviar certificado.");
      }
    } catch (error) {
      console.error("Erro ao enviar certificado:", error);
    }
  };
  return (
    <View className="flex-row border-b border-slate-300">
      {/* TITULO */}
      <View className="w-4/12 p-2 justify-center">
        <Text className="text-lg color-slate-500">{participante}</Text>
      </View>

      {/* TITULO */}
      <View className="w-4/12 p-2 justify-center">
        <Text className="text-lg color-slate-500">{email}</Text>
      </View>

      {/* STATUS */}
      <View className="w-2/12 p-2 justify-center items-center">
      {status === 1 ? (
        <Pressable className="bg-[#FF0004] opacity-50 rounded-lg px-2 py-1">
          <Text className="text-white text-center">Não enviado</Text>
        </Pressable>
      ) : (
        <Pressable className="bg-[#2192ff] opacity-50 rounded-lg px-2 py-1">
          <Text className="text-white text-center">Enviado</Text>
        </Pressable>
      )}
    </View>

      {/* OPÇÕES */}
      <View className="w-2/12 p-2 flex-row gap-1 justify-center items-center">
        <Pressable onPress={enviarCertificado} className="flex-row border border-slate-400 rounded-lg px-2 py-1 justify-center items-center">
          <Ionicons name="checkmark" size={16} className="color-slate-400" />
          <Text className="color-slate-400 ml-1">Publicar</Text>
        </Pressable>
        <Pressable className="flex-row border border-slate-400 rounded-lg px-2 py-1 justify-center items-center">
          <Ionicons name="paper-plane-outline" size={16} className="color-slate-400" />
          <Text className="color-slate-400 ml-1">Envio</Text>
        </Pressable>
      </View>
    </View>
  );
}