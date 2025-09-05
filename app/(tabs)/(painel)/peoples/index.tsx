import "../../../../style/global.css";

import React from 'react';
import { Text, View, Image, ScrollView, Pressable, TextInput } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { InfoBox, PeopleBox } from "@/components/InfoBox";

export default function Profile() {
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
                <Text className="text-2xl">Pessoas</Text>
                <View className="flex-row items-center justify-between mt-4">
                    <View className="flex-row items-center gap-2 mt-2">
                        <Pressable className="w-40 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"><Ionicons name="add" size={22}/>Adicionar Pessoa</Pressable>
                        <Pressable className="w-40 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"><Ionicons name="mail-outline" size={22}/>Notificar Pessoa</Pressable>
                        <Pressable className="w-20 flex-row border border-slate-500 rounded-lg justify-center items-center p-1"><Ionicons name="chevron-down-outline" size={22}/>Ações</Pressable>
                    </View>
                    <View className="flex-row items-center gap-2 mt-2 border border-slate-500 rounded-lg p-0.5">
                        <TextInput placeholder="Buscar Por Nome ou E-mail..." className="flex-1 bg-transparent color-slate-500 dark:color-white text-lg"/>
                        <Ionicons name="search" size={22} className="color-slate-700 mr-2" />
                    </View>
                </View>
                <View className="flex-row flex-wrap gap-4 mt-4">
                    <PeopleBox photo="user.png" name="Nome da pessoa" email="nome@mail.com"></PeopleBox>
                    <PeopleBox photo="user.png" name="Nome da pessoa" email="nome@mail.com"></PeopleBox>
                    <PeopleBox photo="user.png" name="Nome da pessoa" email="nome@mail.com"></PeopleBox>
                    <PeopleBox photo="user.png" name="Nome da pessoa" email="nome@mail.com"></PeopleBox>
                    <PeopleBox photo="user.png" name="Nome da pessoa" email="nome@mail.com"></PeopleBox>
                    <PeopleBox photo="user.png" name="Nome da pessoa" email="nome@mail.com"></PeopleBox>
                    <PeopleBox photo="user.png" name="Nome da pessoa" email="nome@mail.com"></PeopleBox>
                    <PeopleBox photo="user.png" name="Nome da pessoa" email="nome@mail.com"></PeopleBox>
                </View>
            </View>
        </Mainframe>

        

    </ScrollView>
  );
}

