import "../../../../style/global.css";

import React from 'react';
import { Text, View, Image, ScrollView, Pressable, TextInput } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { CertifyBox, InfoBox, ParticipantCertifyBox, PeopleBox } from "@/components/InfoBox";
import { router } from "expo-router";

export default function SendCerticate() {
  return (
    <ScrollView className="flex-1 dark:bg-black">
        <Mainframe name="SICAD - Evento de Teste " photoUrl="evento.png" link="www.evento.com">
          <View className="px-8">
            <Text className="text-2xl dark:color-white">Certificados</Text>
              <View className="flex-row items-center justify-between my-2">
                  <View className="flex-row items-center gap-2 mt-2">
                      <Pressable
                        onPress={() => router.push("./page")} // <- chama a rota como função
                        className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"
                      >
                        <Ionicons name="add" size={22} />
                        <Text>Criar</Text>
                      </Pressable>

                      <Pressable
                        onPress={() => router.push("./send")}
                        className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"
                      >
                        <Ionicons name="mail-outline" size={22} />
                        <Text className="text-nowrap">Envio por E-mail</Text>
                      </Pressable>

                      <Pressable
                        onPress={() => router.push("./settings")}
                        className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"
                      >
                        <Ionicons name="settings-outline" size={22} />
                        <Text>Configurações</Text>
                      </Pressable>
                  </View>
                </View>
              <View className="flex-row items-center justify-between mt-4 border-b-2 border-slate-300 pb-2">
                <Text className="text-2xl dark:color-white">Enviar Certificados</Text>  
                <View className="flex-row mt-2 gap-2">
                  <TextInput placeholder="Buscar" className="w-min bg-transparent border border-slate-400 rounded-lg px-2 py-1 color-slate-500 dark:color-white"/>
                  <Pressable className="w-min bg-[#2192ff] items-center justify-center rounded-lg px-2 py-1 color-white">Exportar</Pressable>
                  <Pressable className="w-min bg-[#2192ff] items-center justify-center rounded-lg px-2 py-1 color-white text-nowrap">Enviar para todos</Pressable>
                </View>
              </View>
              <View className='flex-row justify-center items-center border-b border-slate-300 px-4'>
                <Text className='w-5/12 font-semibold color-slate-400'>PARTICIPANTE</Text>
                <Text className='w-4/12 font-semibold color-slate-400'>E-MAIL</Text>
                <Text className='w-2/12 font-semibold color-slate-400'>STATUS</Text>
                <Text className='w-2/12 font-semibold color-slate-400'>OPÇÕES</Text>
              </View>
              <ParticipantCertifyBox participante="Lorenzo Jordani Bertozzi Luz" email="lorenzobertozzi847@gmail.com" status={0}></ParticipantCertifyBox>
              <ParticipantCertifyBox participante="Lukas Julius Wolf" email="lukasjuliuswolf@gmail.com" status={1}></ParticipantCertifyBox>
          </View>
        </Mainframe>
    </ScrollView>
  );
}

