import "../../../../style/global.css";

import React from 'react';
import { Text, View, Image, ScrollView, Pressable, TextInput } from 'react-native';
import { router } from "expo-router";
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { CertifyBox, InfoBox, PeopleBox } from "@/components/InfoBox";

export default function Certicate() {
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
                  <Text className="text-2xl dark:color-white">Criar Certificados</Text>
                  <Pressable className="w-min text-nowrap color-white bg-[#2192ff] rounded-lg justify-center items-center p-1 mt-2">Adicionar Certificado</Pressable>
                </View>
                <View className='flex-row justify-center items-center border-b border-slate-300 px-4'>
                  <Text className='w-4/12 font-semibold color-slate-400'>TITULO</Text>
                  <Text className='w-2/12 font-semibold color-slate-400'>VALOR</Text>
                  <Text className='w-2/12 font-semibold color-slate-400'>MODELO</Text>
                  <Text className='w-2/12 font-semibold color-slate-400'>ATRIBUIÇÃO</Text>
                  <Text className='w-2/12 font-semibold color-slate-400'>STATUS</Text>
                  <Text className='w-2/12 font-semibold color-slate-400'>OPÇÕES</Text>
                </View>
                <CertifyBox titulo="Certificado de Participação" valor={0} modelo="Padrão" att="Automática" status="Ativo"></CertifyBox>
                <CertifyBox titulo="MiniCurso Web" valor={9.99} modelo="Padrão" att="Automática" status="Ativo"></CertifyBox>
                <CertifyBox titulo="Semana de 115 anos" valor={13.00} modelo="Padrão" att="Automática" status="Ativo"></CertifyBox>
            </View>
        </Mainframe>
    </ScrollView>
  );
}

