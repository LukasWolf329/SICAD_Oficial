import "../../../../style/global.css";

import React, { useEffect, useState } from "react";
import { Text, View, Image, ScrollView, Pressable } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';
import AsyncStorage from "@react-native-async-storage/async-storage";
import { Mainframe, NavBar, SideBar, SideBarCategory } from '../../../../components/NavBar';
import { InfoBox } from "@/components/InfoBox";
import { useLocalSearchParams, router } from "expo-router";
import { setLastEventoId, setLastEventoNome } from "../../../utils/lastEvento";

export default function HomePage() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const rawId = Array.isArray(params.id) ? params.id[0] : params.id; // garante string
  const eventoId = rawId ? Number(rawId) : NaN;

  const [totalInscritos, setTotalInscritos] = useState(0);
  const [atividadesCadastradas, setAtividadesCadastradas] = useState(0);
  const [totalCertificados, setTotalCertificados] = useState(0);
  const [eventoNome, setEventoNome] = useState("Evento");

  // 1) Se entrou aqui sem id, tenta recuperar do storage e redireciona
  useEffect(() => {
    (async () => {
      if (rawId) return;

      const userId = await AsyncStorage.getItem("userId");
      const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
      const lastEventoId = await AsyncStorage.getItem(key);

      if (lastEventoId) {
        router.replace({
          pathname: "/(tabs)/(painel)/home/page",
          params: { id: lastEventoId },
        });
      } else {
        router.replace("/(tabs)/(painel)/home/page"); // ajuste para sua rota real
      }
    })();
  }, [rawId]);

  // 2) Sempre que tiver id válido, salva como "último evento"
  useEffect(() => {
    (async () => {
      if (!rawId || Number.isNaN(eventoId)) return;

      const userId = await AsyncStorage.getItem("userId");
      const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
      await AsyncStorage.setItem(key, String(eventoId));
    })();
  }, [rawId, eventoId]);

  // 3) Fetch do backend (agora depende do id!)
  useEffect(() => {
    if (!rawId || Number.isNaN(eventoId)) return;

    const controller = new AbortController();

    fetch("http://192.168.1.9/SICAD/page-org.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ evento_id: eventoId }),
      signal: controller.signal,
    })
      .then((res) => res.json())
      .then(async (json) => {
        const nome = json.evento_nome ?? "Evento";

        setEventoNome(nome);
        setTotalInscritos(json.total_participantes ?? 0);
        setAtividadesCadastradas(json.atividades_cadastradas ?? 0);
        setTotalCertificados(json.total_certificados ?? 0);
        const userId = await AsyncStorage.getItem("userId");
        await setLastEventoId(eventoId, userId);     
        await setLastEventoNome(nome, userId);

      })

      .catch((err) => {
        if (err?.name !== "AbortError") {
          console.error("Erro ao buscar os dados:", err);
        }
      });

    return () => controller.abort();
  }, [rawId, eventoId]);

  return (
    <ScrollView className="flex-1 dark:bg-[#121212]">

      <Mainframe name={eventoNome} photoUrl="user.png" link="www.evento.com">
        <View className="flex-row justify-center gap-4">
          <InfoBox name="Total de Inscritos" icon="people" counter={(totalInscritos ?? 0).toString()}></InfoBox>
          <InfoBox name="Certificados Emitidos" icon="card-outline" counter={(totalCertificados ?? 0).toString()}></InfoBox>
          <InfoBox name="Total de Atividades" icon="book-outline" counter={(atividadesCadastradas ?? 0).toString()}></InfoBox>
        </View>


        <View className="p-8">
          <Text className="text-2xl dark:color-white mb-8">Planejamento</Text>
          <View className="flex-row justify-between items-center mb-8">
            <View className="flex-row items-center gap-2">
              <Ionicons name="checkmark-circle" size={48} className="color-green-600" />
              <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
            </View>
            <View>
              <Pressable className="border dark:border-[#e0e0e0] rounded-xl px-4 py-2 w-min text-xl dark:color-[#e0e0e0]">Acesse</Pressable>
            </View>
          </View>
          <View className="flex-row justify-between items-center mb-8">
            <View className="flex-row items-center gap-2">
              <Ionicons name="checkmark-circle" size={48} className="color-slate-300" />
              <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
            </View>
            <View>
              <Pressable className="border dark:border-[#e0e0e0] rounded-xl px-4 py-2 w-min text-xl dark:color-[#e0e0e0]">Acesse</Pressable>
            </View>
          </View>
          <View className="flex-row justify-between items-center mb-8">
            <View className="flex-row items-center gap-2">
              <Ionicons name="checkmark-circle" size={48} className="color-slate-300" />
              <Text className="text-xl dark:color-white">Crie Seu Evento</Text>
            </View>
            <View>
              <Pressable className="border dark:border-[#e0e0e0] rounded-xl px-4 py-2 w-min text-xl dark:color-[#e0e0e0]">Acesse</Pressable>
            </View>
          </View>
          <View className="flex-row justify-between items-center mb-8">
            <View className="flex-row items-center gap-2">
              <Ionicons name="checkmark-circle" size={48} className="color-slate-300" />
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

