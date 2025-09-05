import "../../../../style/global.css";

import React from 'react';
import { Text, View, Image, ScrollView, Pressable } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';

export default function Profile() {
  return (
    <ScrollView className="flex-1 bg-slate-50 dark:bg-black">
        <NavBar></NavBar>
        
        <SideBar>
            <SideBarCategory
                titulo="Categoria Exemplo"
                itens={[
                    { nome: "Inicio", icone: "home-outline", link: "../../index.tsx" },
                    { nome: "Usuários", icone: "people", link: "/usuarios" },
                    { nome: "Configurações", icone: "settings-outline", link: "/config" }
                ]}
            ></SideBarCategory>
        </SideBar>

        <Mainframe name="Nome do Evento" photoUrl="user.png"></Mainframe>
    </ScrollView>
  );
}
