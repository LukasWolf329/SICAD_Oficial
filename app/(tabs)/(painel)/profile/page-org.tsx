import "../../../../style/global.css";

import React from 'react';
import { Text, View, Image, ScrollView, Pressable } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { InfoBox } from "@/components/InfoBox";

export default function Profile() {
  return (
    <ScrollView className="flex-1 bg-slate-50 dark:bg-black">
        <NavBar></NavBar>
        
        <SideBar>
            <SideBarCategory
                titulo="Gestão"
                itens={[
                    { nome: "Inicio", icone: "home-outline", link: "" },
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

        <Mainframe name="Nome do Evento" photoUrl="user.png" link="www.evento.com">
            <View className="flex-row justify-center gap-4">
                <InfoBox name="Total de Inscritos" icon="people" counter="200"></InfoBox>
                <InfoBox name="Certificados Emitidos" icon="card-outline" counter="143"></InfoBox>
                <InfoBox name="Total de Inscritos" icon="book-outline" counter="200"></InfoBox>
            </View>


            <View className="p-8">
                <Text className="text-2xl dark:color-white mb-8">Planejamento</Text>
                <View className="flex-row justify-between items-center mb-8">
                    <View className="flex-row items-center gap-2">
                        <Ionicons name="checkmark-circle" size={48} className="color-green-600"/>
                        <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
                    </View>
                    <View>
                        <Pressable className="border rounded-xl px-4 py-2 w-min text-xl">Acesse</Pressable>
                    </View>
                </View>
                <View className="flex-row justify-between items-center mb-8">
                    <View className="flex-row items-center gap-2">
                        <Ionicons name="checkmark-circle" size={48} className="color-slate-300"/>
                        <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
                    </View>
                    <View>
                        <Pressable className="border rounded-xl px-4 py-2 w-min text-xl">Acesse</Pressable>
                    </View>
                </View>
                <View className="flex-row justify-between items-center mb-8">
                    <View className="flex-row items-center gap-2">
                        <Ionicons name="checkmark-circle" size={48} className="color-slate-300"/>
                        <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
                    </View>
                    <View>
                        <Pressable className="border rounded-xl px-4 py-2 w-min text-xl">Acesse</Pressable>
                    </View>
                </View>
                <View className="flex-row justify-between items-center mb-8">
                    <View className="flex-row items-center gap-2">
                        <Ionicons name="checkmark-circle" size={48} className="color-slate-300"/>
                        <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
                    </View>
                    <View>
                        <Pressable className="border rounded-xl px-4 py-2 w-min text-xl">Acesse</Pressable>
                    </View>
                </View>
            </View>
        </Mainframe>

    </ScrollView>
  );
}

