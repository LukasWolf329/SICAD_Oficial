import "../../../../style/global.css";

import React, { useEffect, useState, useMemo } from 'react';
import { Text, View, ScrollView, Pressable, TextInput } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { Mainframe, PeopleBox } from "@/components/InfoBox";

import { useLocalSearchParams, router } from "expo-router";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { getLastEventoNome } from "../../../utils/lastEvento"; // ajuste o caminho

import * as DocumentPicker from 'expo-document-picker';

  type Pessoa = {
    nome: string;
    email: string;
  };

  export default function Profile() {
    // 1) pega id da rota igual no seu exemplo
    const params = useLocalSearchParams<{ id?: string | string[] }>();
    const rawId = Array.isArray(params.id) ? params.id[0] : params.id;
    const eventoId = useMemo(() => (rawId ? Number(rawId) : NaN), [rawId]);

    const [pessoas, setPessoas] = useState<Pessoa[]>([]);
    const [eventoNome, setEventoNome] = useState<string>("Evento");
    const [showActions, setShowActions] = useState(false);

    const exportarCSV = () => {
      if (!eventoId || Number.isNaN(eventoId)) return;

      window.open(
        `http://localhost/SICAD_Oficial/controller/exportar_participantes.php?atividade_id=${eventoId}`
      );
    };

    const importarCSV = async () => {
      if (!eventoId || Number.isNaN(eventoId)) return;

      const result = await DocumentPicker.getDocumentAsync({
        type: "text/csv",
      });

      if (!result.assets) return;

      const file = result.assets[0];

      const formData = new FormData();
      formData.append("arquivo", file as any);
      formData.append("atividade_id", String(eventoId));

      try {
        const response = await fetch(
          "http://localhost/SICAD_Oficial/controller/importar_participantes.php",
          {
            method: "POST",
            body: formData,
          }
        );

        const data = await response.json();

        if (data.status === "ok") {
          alert("Importação concluída!");

          setTimeout(() => {
            router.replace({
              pathname: "/(tabs)/(painel)/peoples/page",
              params: { id: eventoId },
            });
          }, 500);
        }
      } catch (err) {
        console.error("Erro ao importar:", err);
      }
    };

    // (opcional, mas igual ao seu padrão) se entrou sem id, tenta recuperar o último evento e redireciona
    useEffect(() => {
      (async () => {
        if (rawId) return;

        const userId = await AsyncStorage.getItem("userId");
        const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
        const lastEventoId = await AsyncStorage.getItem(key);

        if (lastEventoId) {
          router.replace({
            pathname: "/(tabs)/(painel)/peoples/page",
            params: { id: lastEventoId },
          });
        }
      })();
    }, [rawId]);

    // 2) salva como último evento quando tiver id válido
    useEffect(() => {
      (async () => {
        if (!rawId || Number.isNaN(eventoId)) return;

        const userId = await AsyncStorage.getItem("userId");
        const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
        await AsyncStorage.setItem(key, String(eventoId));
      })();
    }, [rawId, eventoId]);

    // 3) fetch do backend DEPENDENDO do eventoId
    useEffect(() => {
      if (!rawId || Number.isNaN(eventoId)) return;

      const controller = new AbortController();

      fetch("../../../../SICAD_Oficial/people.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ evento_id: eventoId }),
        signal: controller.signal,
      })
        .then((res) => res.json())
        .then((data) => {
          // se o seu PHP devolver { pessoas: [...], evento_nome: "..." }
          if (Array.isArray(data?.pessoas)) {
            setPessoas(data.pessoas);
          } else {
            // se devolver direto um array
            setPessoas(Array.isArray(data) ? data : []);
          }
          if (typeof data?.evento_nome === "string" && data.evento_nome.trim() !== "") {
            setEventoNome(data.evento_nome);
          }

        })
        .catch((err) => {
          if (err?.name !== "AbortError") {
            console.error("Erro ao carregar pessoas:", err);
          }
        });

      return () => controller.abort();
    }, [rawId, eventoId]);

    useEffect(() => {
      (async () => {
        const userId = await AsyncStorage.getItem("userId");
        const nome = await getLastEventoNome(userId);
        if (nome) setEventoNome(nome);
      })();
    }, []);




    return (
      <ScrollView className="flex-1 dark:bg-[#121212]">

        <Mainframe name={eventoNome} link="www.evento.com">
          <View className="px-8 relative">
            <Text className="text-2xl dark:color-white">Pessoas</Text>
            <View className="flex-row items-center justify-between mt-4">
              <View className="flex-row items-center gap-2 mt-2">
                <Pressable className="w-40 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"><Ionicons name="add" size={22} />Adicionar Pessoa</Pressable>
                <Pressable className="w-40 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"><Ionicons name="mail-outline" size={22} />Notificar Pessoa</Pressable>
                <Pressable className="w-20 flex-row border border-slate-500 rounded-lg justify-center items-center p-1" onPress={() => setShowActions(!showActions)}><Ionicons name="chevron-down-outline" size={22}/>Ações</Pressable>

                {showActions && (
                  <View className="absolute bg-white border rounded mt-2 p-2 z-50">
                    <Pressable onPress={exportarCSV}><Text>Exportar CSV</Text></Pressable>
                    <Pressable onPress={importarCSV}><Text>Importar CSV</Text></Pressable>
                  </View>
                )}
              </View>
            </View>
            <View className="flex-row items-center gap-2 my-4 border border-slate-500 rounded-lg p-0.5 w-4/12">
              <TextInput placeholder="Buscar Por Nome ou E-mail..." className="flex-1 bg-transparent color-slate-500 dark:color-white text-lg" />
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

