import "../../../../style/global.css";

import React from 'react';
import { Text, View, Image, ScrollView, Pressable } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { InfoBox } from "@/components/InfoBox";

export default function HomePage() {
  return (
    <ScrollView className="flex-1 dark:bg-[#121212]">

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
                        <Pressable className="border dark:border-[#e0e0e0] rounded-xl px-4 py-2 w-min text-xl dark:color-[#e0e0e0]">Acesse</Pressable>
                    </View>
                </View>
                <View className="flex-row justify-between items-center mb-8">
                    <View className="flex-row items-center gap-2">
                        <Ionicons name="checkmark-circle" size={48} className="color-slate-300"/>
                        <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
                    </View>
                    <View>
                        <Pressable className="border dark:border-[#e0e0e0] rounded-xl px-4 py-2 w-min text-xl dark:color-[#e0e0e0]">Acesse</Pressable>
                    </View>
                </View>
                <View className="flex-row justify-between items-center mb-8">
                    <View className="flex-row items-center gap-2">
                        <Ionicons name="checkmark-circle" size={48} className="color-slate-300"/>
                        <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
                    </View>
                    <View>
                        <Pressable className="border dark:border-[#e0e0e0] rounded-xl px-4 py-2 w-min text-xl dark:color-[#e0e0e0]">Acesse</Pressable>
                    </View>
                </View>
                <View className="flex-row justify-between items-center mb-8">
                    <View className="flex-row items-center gap-2">
                        <Ionicons name="checkmark-circle" size={48} className="color-slate-300"/>
                        <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
                    </View>
                    <View>
                        <Pressable className="border dark:border-[#e0e0e0] rounded-xl px-4 py-2 w-min text-xl dark:color-[#e0e0e0]">Acesse</Pressable>
                    </View>
                </View>
            </View>
        </Mainframe>

    </ScrollView>
  );
}

