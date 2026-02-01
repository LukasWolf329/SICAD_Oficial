import "../../../../style/global.css";


import { Text, View, Image, ScrollView, Pressable, TextInput } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { CertifyBox, InfoBox, ParticipantCertifyBox, PeopleBox } from "@/components/InfoBox";
import { router } from "expo-router";
import { CheckBox } from "react-native-web";
import { getLastEventoNome } from "@/app/utils/lastEvento";
import React, { useEffect, useState } from "react";
import AsyncStorage from "@react-native-async-storage/async-storage";


export default function SendCerticate() {
  const [eventoNome, setEventoNome] = useState<string>("Evento");
  useEffect(() => {
    (async () => {
      const userId = await AsyncStorage.getItem("userId");
      const nome = await getLastEventoNome(userId);
      if (nome) setEventoNome(nome);
    })();
  }, []);

  return (
    <ScrollView className="flex-1  dark:bg-black">
        <Mainframe name={eventoNome} photoUrl="evento.png" link="www.evento.com">
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
              <View className="flex-row items-center justify-between mt-4 border-b-2 border-slate-300 pb-4">
                <Text className="text-2xl dark:color-white">Configurações</Text>  
              </View>
              <View className="flex-row items-center justify-between mt-4 border-b-2 border-slate-300 pb-4">
                <View>
                  <Text className="text-lg font-semibold dark:color-white">Comunicação</Text>
                  <Text className="dark:color-white">Edite o conteudo do email que o particiamnete vcai receber ao ser enviado o Certificado</Text>  
                </View>
                <View>
                  <Pressable className="w-min border border-slate-400 items-center justify-center rounded-lg px-2 py-1 dark:color-white text-nowrap flex-row gap-2"><Ionicons name="mail-outline" size={22} className="dark:color-white"/> Editar E-mail</Pressable>
                </View>
              </View>
              <View className="flex-row items-center justify-between mt-4 border-slate-300 pb-4">
                <View>
                  <Text className="text-lg font-semibold dark:color-white">Formato da Publicação</Text>
                  <Text className="dark:color-white">Defina como os certificados são ser liberados</Text>  
                </View>
                
              </View>
              <View className="flex-row gap-2 mb-8">
                <View>                  
                  <CheckBox value={true} />
                </View>
                <View>                  
                  <Text className="dark:color-white font-semibold">Publicar Automaticamente</Text>
                  <Text className="dark:color-white">Os certidicado seçao liberado automaticamente 7 dias apos o evento</Text>
                </View>
              </View>
              <View className="flex-row gap-2 mb-8">
                <View>                  
                  <CheckBox value={false} />
                </View>
                <View>                  
                  <Text className="dark:color-white font-semibold">Publicar Manualmente</Text>
                  <Text className="dark:color-white">Voce precisa publicar manualmente para os certificadosficarem disponiveis</Text>
                </View>
              </View>
              <View>
                <Pressable className="w-min bg-[#2192ff] items-center justify-center rounded-lg px-2 py-1 color-white text-nowrap">Salvar Configurações</Pressable>
              </View>
          </View>
        </Mainframe>
    </ScrollView>
  );
}

