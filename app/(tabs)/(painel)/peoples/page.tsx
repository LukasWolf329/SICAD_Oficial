import "../../../../style/global.css";

import React, { useEffect, useState } from 'react';
import { Text, View, Image, ScrollView, Pressable, TextInput } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { InfoBox, PeopleBox } from "@/components/InfoBox";

type Pessoa = {
  nome: string;
  email: string;
};

export default function Profile() {
    const[pessoas, setPessoas] = useState<Pessoa[]>([]);
    useEffect(() => {
        fetch("http://200.18.141.92/SICAD/people.php")
        .then((res) => res.json())
        .then((data) => setPessoas(data))
        .catch((err) => console.error("Erro ao carregar pessoas:", err))
    }, [])

  return (
    <ScrollView className="flex-1 dark:bg-[#121212]">

        <Mainframe name="SICAD - Evento de Teste " photoUrl="evento.png" link="www.evento.com">
            <View className="px-8">
                <Text className="text-2xl dark:color-white">Pessoas</Text>
                <View className="flex-row items-center justify-between mt-4">
                    <View className="flex-row items-center gap-2 mt-2">
                        <Pressable className="w-40 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"><Ionicons name="add" size={22}/>Adicionar Pessoa</Pressable>
                        <Pressable className="w-40 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"><Ionicons name="mail-outline" size={22}/>Notificar Pessoa</Pressable>
                        <Pressable className="w-20 flex-row border border-slate-500 rounded-lg justify-center items-center p-1 dark:color-white"><Ionicons className="dark:color-white" name="chevron-down-outline" size={22}/>Ações</Pressable>
                    </View>
                </View>
                <View className="flex-row items-center gap-2 my-4 border border-slate-500 rounded-lg p-0.5 w-4/12">
                    <TextInput placeholder="Buscar Por Nome ou E-mail..." className="flex-1 bg-transparent color-slate-500 dark:color-white text-lg"/>
                    <Ionicons name="search" size={22} className="color-slate-700 mr-2 dark:color-white" />
                </View>
                <View className="flex-row flex-wrap gap-4 mt-4">
                    {pessoas.map((pessoa, index) => 
                        <PeopleBox
                            key={index}
                            photo="user.png"
                            name={pessoa.nome}
                            email={pessoa.email}
                        />
                    )}
                </View>
            </View>
        </Mainframe>

        

    </ScrollView>
  );
}

