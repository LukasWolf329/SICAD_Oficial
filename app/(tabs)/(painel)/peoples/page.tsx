import "../../../../style/global.css";

import React, { useEffect, useState, useMemo } from "react";
import {
  Text,
  View,
  ScrollView,
  Pressable,
  TextInput,
  ActivityIndicator,
  Alert,
} from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { Mainframe } from "../../../../components/NavBar";
import { useLocalSearchParams, router } from "expo-router";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as DocumentPicker from "expo-document-picker";
import * as Linking from "expo-linking";

type Pessoa = {
  nome: string;
  email: string;
};

const API_BASE = "http://localhost/SICAD_Oficial/controller"; // ajuste se necessário

export default function Profile() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const rawId = Array.isArray(params.id) ? params.id[0] : params.id;
  const atividadeId = useMemo(() => (rawId ? Number(rawId) : NaN), [rawId]);

  const [pessoas, setPessoas] = useState<Pessoa[]>([]);
  const [eventoNome, setEventoNome] = useState<string>("Evento");
  const [showActions, setShowActions] = useState(false);
  const [loading, setLoading] = useState(false);

  /* ================= EXPORTAR ================= */

  const exportarCSV = () => {
    if (!atividadeId || Number.isNaN(atividadeId)) return;

    const url = `${API_BASE}/exportar_participantes.php?atividade_id=${atividadeId}`;
    Linking.openURL(url);
  };

  /* ================= IMPORTAR ================= */

  const importarCSV = async () => {
    if (!atividadeId || Number.isNaN(atividadeId)) return;

    const result = await DocumentPicker.getDocumentAsync({
      type: "text/csv",
      copyToCacheDirectory: true,
    });

    if (result.canceled) return;

    const file = result.assets[0];

    const formData = new FormData();
    formData.append("arquivo", {
      uri: file.uri,
      name: file.name,
      type: "text/csv",
    } as any);

    formData.append("atividade_id", String(atividadeId));

    try {
      setLoading(true);

      const response = await fetch(
        `${API_BASE}/importar_participantes.php`,
        {
          method: "POST",
          body: formData,
        }
      );

      const text = await response.text();
      const data = JSON.parse(text);

      if (data.status === "ok") {
        Alert.alert("Sucesso", "Importação concluída!");
        carregarPessoas();
      } else {
        Alert.alert("Erro", data.msg || "Erro na importação");
      }
    } catch (err) {
      console.error(err);
      Alert.alert("Erro", "Falha ao importar CSV");
    } finally {
      setLoading(false);
    }
  };

  /* ================= CARREGAR PESSOAS ================= */

  const carregarPessoas = async () => {
    if (!atividadeId || Number.isNaN(atividadeId)) return;

    try {
      setLoading(true);

      const response = await fetch(`${API_BASE}/people.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ evento_id: atividadeId }),
      });

      const data = await response.json();

      if (Array.isArray(data?.pessoas)) {
        setPessoas(data.pessoas);
      } else {
        setPessoas(Array.isArray(data) ? data : []);
      }

      if (data?.evento_nome) {
        setEventoNome(data.evento_nome);
      }
    } catch (err) {
      console.error("Erro ao carregar pessoas:", err);
    } finally {
      setLoading(false);
    }
  };

  /* ================= USE EFFECTS ================= */

  useEffect(() => {
    if (!rawId) return;
    carregarPessoas();
  }, [rawId]);

  /* ================= UI ================= */

  return (
    <ScrollView className="flex-1 dark:bg-[#121212]">
      <Mainframe name={eventoNome} link="www.evento.com">
        <View className="px-8">
          <Text className="text-2xl dark:color-white">Pessoas</Text>

          {/* BOTÕES */}
          <View className="flex-row items-center gap-2 mt-4">
            <Pressable
              className="w-40 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-2"
              onPress={() => setShowActions(!showActions)}
            >
              <Ionicons name="settings-outline" size={20} />
              <Text className="ml-2">Ações</Text>
            </Pressable>
          </View>

          {/* MENU AÇÕES */}
          {showActions && (
            <View className="mt-2 bg-white border rounded-lg p-2 shadow-md">
              <Pressable
                onPress={() => {
                  setShowActions(false);
                  exportarCSV();
                }}
                className="p-2"
              >
                <Text>Exportar CSV</Text>
              </Pressable>

              <Pressable
                onPress={() => {
                  setShowActions(false);
                  importarCSV();
                }}
                className="p-2"
              >
                <Text>Importar CSV</Text>
              </Pressable>
            </View>
          )}

          {/* BUSCA */}
          <View className="flex-row items-center gap-2 my-4 border border-slate-500 rounded-lg p-1">
            <TextInput
              placeholder="Buscar por Nome ou Email..."
              className="flex-1 text-lg"
            />
            <Ionicons name="search" size={22} />
          </View>

          {/* LOADING */}
          {loading && (
            <ActivityIndicator size="large" color="#9BEC00" />
          )}

          {/* LISTA */}
          <View className="flex-row flex-wrap gap-4 mt-4">
            {pessoas.map((pessoa, index) => (
              <View
                key={index}
                className="border rounded-lg p-4 w-64 bg-white"
              >
                <Text className="font-bold">{pessoa.nome}</Text>
                <Text>{pessoa.email}</Text>
              </View>
            ))}
          </View>
        </View>
      </Mainframe>
    </ScrollView>
  );
}