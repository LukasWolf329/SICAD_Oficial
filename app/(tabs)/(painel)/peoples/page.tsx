import "../../../../style/global.css";

import React, { useEffect, useState } from "react";
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
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as DocumentPicker from "expo-document-picker";
import * as Linking from "expo-linking";
import { useLocalSearchParams, router } from "expo-router";
import { setLastEventoId, setLastEventoNome } from "../../../utils/lastEvento";
import { useEventosModal } from "@/components/NavBar/EventosModalContext";

type Pessoa = {
  nome: string;
  email: string;
};

const API_BASE = "http://localhost/SICAD_Oficial/controller";

export default function HomePage() {
  /* ================= PARAMS ================= */

  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const rawId = Array.isArray(params.id) ? params.id[0] : params.id;
  const eventoId = rawId ? Number(rawId) : NaN;

  const { openEventos } = useEventosModal();

  /* ================= STATES ================= */

  const [eventoNome, setEventoNome] = useState<string>("Evento");
  const [pessoas, setPessoas] = useState<Pessoa[]>([]);
  const [showActions, setShowActions] = useState(false);
  const [loading, setLoading] = useState(false);

  /* ============================================================
     1) Se entrou sem ID → tenta recuperar do AsyncStorage
  ============================================================ */

  useEffect(() => {
    let ativo = true;

    (async () => {
      if (rawId) return;

      const userId = await AsyncStorage.getItem("userId");
      const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
      const lastEventoId = await AsyncStorage.getItem(key);

      if (!ativo) return;

      if (lastEventoId) {
        router.replace({
          pathname: "/(tabs)/(painel)/peoples/page",
          params: { id: lastEventoId },
        });
      } else {
        openEventos();
      }
    })();

    return () => {
      ativo = false;
    };
  }, [rawId, openEventos]);

  /* ============================================================
     2) Sempre que tiver ID válido → salva como último evento
  ============================================================ */

  useEffect(() => {
    (async () => {
      if (!rawId || Number.isNaN(eventoId)) return;

      const userId = await AsyncStorage.getItem("userId");
      const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
      await AsyncStorage.setItem(key, String(eventoId));
    })();
  }, [rawId, eventoId]);

  /* ============================================================
     3) Buscar dados gerais do evento
  ============================================================ */

  useEffect(() => {
    if (!rawId || Number.isNaN(eventoId)) return;

    const controller = new AbortController();

    (async () => {
      fetch(`${API_BASE}/page-org.php`, {
        method: "POST",
        body: JSON.stringify({ evento_id: eventoId }),
        signal: controller.signal,
      })
        .then((res) => res.json())
        .then(async (json) => {
          const nome = json.evento_nome ?? "Evento";

          setEventoNome(nome);

          const userId = await AsyncStorage.getItem("userId");
          await setLastEventoId(eventoId, userId);
          await setLastEventoNome(nome, userId);
        })
        .catch((err) => {
          if (err?.name !== "AbortError") {
            console.error("Erro ao buscar dados:", err);
          }
        });
    })();

    return () => controller.abort();
  }, [rawId, eventoId]);

  /* ============================================================
     4) Buscar pessoas do evento
  ============================================================ */

  useEffect(() => {
    if (!rawId || Number.isNaN(eventoId)) return;
    carregarPessoas();
  }, [eventoId]);

  const carregarPessoas = async () => {
    try {
      setLoading(true);

      const response = await fetch(`${API_BASE}/people.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ evento_id: eventoId }),
      });

      const data = await response.json();

      if (Array.isArray(data?.pessoas)) {
        setPessoas(data.pessoas);
      } else {
        setPessoas(Array.isArray(data) ? data : []);
      }
    } catch (err) {
      console.error("Erro ao carregar pessoas:", err);
    } finally {
      setLoading(false);
    }
  };

  /* ================= barra de busca de pessoas ================= */

  const [busca, setBusca] = useState("");

  const pessoasFiltradas = pessoas.filter(p =>
    p.nome.toLowerCase().includes(busca.toLowerCase()) ||
    p.email.toLowerCase().includes(busca.toLowerCase())
  );

  /* ================= EXPORTAR ================= */

  const exportarCSV = () => {
    if (!eventoId || Number.isNaN(eventoId)) return;

    const url = `${API_BASE}/exportar_participantes.php?evento_id=${eventoId}`;
    Linking.openURL(url);
  };

  /* ================= IMPORTAR ================= */
  const importarCSV = async () => {
    if (!eventoId) return;

    const result = await DocumentPicker.getDocumentAsync({
      type: "text/csv",
    });

    if (result.canceled) return;

    const file = result.assets[0];

    console.log("Arquivo selecionado:", file);

    try {
      setLoading(true);

      let formData = new FormData();

      // 🔥 SE FOR WEB (base64)
      if (file.uri.startsWith("data:")) {
        const response = await fetch(file.uri);
        const blob = await response.blob();

        formData.append("arquivo", blob, file.name ?? "arquivo.csv");
      } else {
        // 📱 Mobile normal
        formData.append("arquivo", {
          uri: file.uri,
          name: file.name ?? "arquivo.csv",
          type: "text/csv",
        } as any);
      }

      formData.append("evento_id", String(eventoId));

      const response = await fetch(
        `${API_BASE}/importar_participantes.php`,
        {
          method: "POST",
          body: formData,
        }
      );

      const text = await response.text();
      console.log("RESPOSTA BRUTA:", text);

      const data = JSON.parse(text);

      if (data.status === "ok") {
        Alert.alert("Sucesso", "Importação concluída!");
        carregarPessoas();
      } else {
        Alert.alert("Erro", data.msg || "Erro na importação");
      }

    } catch (err) {
      console.error("Erro fetch:", err);
      Alert.alert("Erro", "Falha ao importar CSV");
    } finally {
      setLoading(false);
    }
  };
  /* ================= UI ================= */

  return (
    <ScrollView className="flex-1 dark:bg-[#121212]">
      <Mainframe name={eventoNome} link="www.evento.com">
        <View className="px-8">

          <Text className="text-2xl dark:text-white">Pessoas</Text>

          {/* Botões */}
          <View className="flex-row items-center gap-2 mt-4">
            <Pressable
              className="w-40 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-2"
              onPress={() => setShowActions(!showActions)}
            >
              <Ionicons name="settings-outline" size={20} />
              <Text className="ml-2">Ações</Text>
            </Pressable>
          </View>

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

          {/* Busca */}
          <View className="flex-row items-center gap-2 my-4 border border-slate-500 rounded-lg p-1">
            <TextInput
              placeholder="Buscar por Nome ou Email..."
              className="flex-1 text-lg"
            />
            <Ionicons name="search" size={22} />
          </View>

          {/* Loading */}
          {loading && (
            <ActivityIndicator size="large" color="#9BEC00" />
          )}

          {/* Lista */}
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