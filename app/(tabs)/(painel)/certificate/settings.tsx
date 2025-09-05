import "../../../../style/global.css";

import React from 'react';
import { Text, View, Image, ScrollView, Pressable, TextInput } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { CertifyBox, InfoBox, PeopleBox } from "@/components/InfoBox";

export default function Settings() {
  return (
    <ScrollView className="flex-1 bg-slate-50 dark:bg-black">
        <NavBar></NavBar>
        
        <SideBar>
            <SideBarCategory
                titulo="Gestão"
                itens={[
                    { nome: "Inicio", icone: "home-outline", link: "/profile/page-org" },
                    { nome: "Pessoas", icone: "people", link: "/peoples" },
                    { nome: "Vendas", icone: "cash", link: "/config" }
                ]}
            ></SideBarCategory>
            <SideBarCategory
                titulo="Pos-Evento"
                itens={[
                    { nome: "Certificados", icone: "map", link: "../certificate" },
                ]}
            ></SideBarCategory>
            <SideBarCategory
                titulo="Geral"
                itens={[
                    { nome: "Configuração", icone: "settings-outline", link: "../../index.tsx" },
                    { nome: "Ferramentas", icone: "hammer-outline", link: "../../index.tsx" }                    
                ]}
            ></SideBarCategory>
        </SideBar>

        <Mainframe name="SICAD - Evento de Teste " photoUrl="evento.png" link="www.evento.com">
            <View className="px-8">
              <Text className="text-2xl">Certificados</Text>
                <View className="flex-row items-center justify-between my-2">
                    <View className="flex-row items-center gap-2 mt-2">
                        <Pressable onPress={require("../certificate/index")} className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"><Ionicons name="add" size={22}/>Criar</Pressable>
                        <Pressable onPress={require("../certificate/send")} className="w-min text-nowrap px-2 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"><Ionicons name="mail-outline" size={22}/>Envio por E-mail</Pressable>
                        <Pressable onPress={require("../certificate/settings")} className="w-min px-2 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"><Ionicons name="settings-outline" size={22}/>Configurações</Pressable>
                    </View>
                  </View>
                <View className="flex-row items-center justify-between mt-4 border-b-2 border-slate-300 pb-2">
                  <Text className="text-2xl">Criar Certificados</Text>
                  <Pressable className="w-min text-nowrap color-white bg-[#2192ff] rounded-lg justify-center items-center p-1 mt-2">Adicionar Certificado</Pressable>
                </View>
                <View className='flex-row justify-between items-center border-b border-slate-300 px-4'>
                  <Text className='font-semibold color-slate-400'>TITULO                           </Text>
                  <Text className='font-semibold color-slate-400'>VALOR</Text>
                  <Text className='font-semibold color-slate-400'>MODELO</Text>
                  <Text className='font-semibold color-slate-400'>ATRIBUIÇÃO</Text>
                  <Text className='font-semibold color-slate-400'>STATUS</Text>
                  <Text className='font-semibold color-slate-400'>OPÇÕES</Text>
                </View>
                <CertifyBox titulo="Certificado de Participação" valor="0" modelo="Padrão" att="Automática" status="Ativo"></CertifyBox>
                <CertifyBox titulo="MiniCurso Web" valor="9.99" modelo="Padrão" att="Automática" status="Ativo"></CertifyBox>
                <CertifyBox titulo="Semana de 115 anos" valor="13.00" modelo="Padrão" att="Automática" status="Ativo"></CertifyBox>
            </View>
        </Mainframe>

        

    </ScrollView>
  );
}

