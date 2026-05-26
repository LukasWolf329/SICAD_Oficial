import "../../../../style/global.css";

import { Ionicons } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as DocumentPicker from "expo-document-picker";
import * as Linking from "expo-linking";
import { router, useLocalSearchParams } from "expo-router";
import React, { useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";
import { Mainframe } from "../../../../components/NavBar";
import { useEventosModal } from "@/components/NavBar/EventosModalContext";
import { setLastEventoId, setLastEventoNome } from "../../../utils/lastEvento";

type ApiObject = Record<string, any>;

type Pessoa = {
  nome: string;
  email: string;
};

const API_BASE = "https://sicad.linceonline.com.br/controller";

function parseApiResponse(raw: unknown): ApiObject | null {
  if (raw && typeof raw === "object") {
    return raw as ApiObject;
  }

  if (typeof raw !== "string") {
    return null;
  }

  const texto = raw.trim();

  if (!texto) {
    return null;
  }

  try {
    return JSON.parse(texto) as ApiObject;
  } catch {
    const inicioObjeto = texto.indexOf("{");
    const inicioArray = texto.indexOf("[");

    let inicioJson = -1;

    if (inicioObjeto >= 0 && inicioArray >= 0) {
      inicioJson = Math.min(inicioObjeto, inicioArray);
    } else {
      inicioJson = Math.max(inicioObjeto, inicioArray);
    }

    if (inicioJson < 0) {
      return null;
    }

    const candidato = texto.slice(inicioJson);

    try {
      return JSON.parse(candidato) as ApiObject;
    } catch {
      const fimObjeto = candidato.lastIndexOf("}");
      const fimArray = candidato.lastIndexOf("]");
      const fimJson = Math.max(fimObjeto, fimArray);

      if (fimJson < 0) {
        return null;
      }

      try {
        return JSON.parse(candidato.slice(0, fimJson + 1)) as ApiObject;
      } catch {
        return null;
      }
    }
  }
}

async function postJsonSafe(url: string, body: unknown, signal?: AbortSignal) {
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
    signal,
  });

  const raw = await res.text();

  console.log("API URL:", url);
  console.log("API STATUS:", res.status);
  console.log("API CONTENT-TYPE:", res.headers.get("content-type"));
  console.log("API RAW:", JSON.stringify(raw));

  const data = parseApiResponse(raw);

  if (!res.ok) {
    throw new Error(data?.msg ?? data?.message ?? `HTTP ${res.status}: ${raw}`);
  }

  if (!data || typeof data !== "object") {
    throw new Error(`Resposta inválida do servidor em ${url}`);
  }

  return data;
}

function isEventoIdValido(eventoId: number) {
  return Number.isFinite(eventoId) && eventoId > 0;
}

export default function HomePage() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const rawId = Array.isArray(params.id) ? params.id[0] : params.id;
  const eventoId = rawId ? Number(rawId) : NaN;

  const { openEventos } = useEventosModal();

  const [eventoNome, setEventoNome] = useState<string>("Evento");
  const [pessoas, setPessoas] = useState<Pessoa[]>([]);
  const [showActions, setShowActions] = useState(false);
  const [loading, setLoading] = useState(false);
  const [busca, setBusca] = useState("");

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

  useEffect(() => {
    (async () => {
      if (!rawId || !isEventoIdValido(eventoId)) return;

      const userId = await AsyncStorage.getItem("userId");
      const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
      await AsyncStorage.setItem(key, String(eventoId));
    })();
  }, [rawId, eventoId]);

  useEffect(() => {
    if (!rawId || !isEventoIdValido(eventoId)) return;

    const controller = new AbortController();

    (async () => {
      try {
        const json = await postJsonSafe(
          `${API_BASE}/page-org.php`,
          { evento_id: eventoId },
          controller.signal
        );

        const nome = String(json.evento_nome ?? "Evento");

        setEventoNome(nome);

        const userId = await AsyncStorage.getItem("userId");
        await setLastEventoId(eventoId, userId);
        await setLastEventoNome(nome, userId);
      } catch (err: any) {
        if (err?.name !== "AbortError") {
          console.error("Erro ao buscar dados do evento:", err);
        }
      }
    })();

    return () => controller.abort();
  }, [rawId, eventoId]);

  async function carregarPessoas() {
    if (!isEventoIdValido(eventoId)) return;

    try {
      setLoading(true);

      const data = await postJsonSafe(`${API_BASE}/people.php`, {
        evento_id: eventoId,
      });

      const listaBruta = Array.isArray(data?.pessoas)
        ? data.pessoas
        : Array.isArray(data)
          ? data
          : [];

      const listaNormalizada = listaBruta.map((p: any) => ({
        nome: String(p.nome ?? ""),
        email: String(p.email ?? ""),
      }));

      console.log("PESSOAS API:", data);
      console.log("PESSOAS NORMALIZADAS:", listaNormalizada);

      setPessoas(listaNormalizada);
    } catch (err) {
      console.error("Erro ao carregar pessoas:", err);
      setPessoas([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (!rawId || !isEventoIdValido(eventoId)) return;
    carregarPessoas();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rawId, eventoId]);

  const pessoasFiltradas = useMemo(() => {
    const termo = busca.trim().toLowerCase();

    if (!termo) {
      return pessoas;
    }

    return pessoas.filter(
      (pessoa) =>
        pessoa.nome.toLowerCase().includes(termo) ||
        pessoa.email.toLowerCase().includes(termo)
    );
  }, [busca, pessoas]);

  async function exportarCSV() {
    if (!isEventoIdValido(eventoId)) {
      Alert.alert("Erro", "Evento inválido.");
      return;
    }

    const url = `${API_BASE}/exportar_participantes.php?evento_id=${encodeURIComponent(String(eventoId))}`;

    try {
      const supported = await Linking.canOpenURL(url);

      if (!supported) {
        Alert.alert("Erro", "Não foi possível abrir o link de exportação.");
        return;
      }

      await Linking.openURL(url);
    } catch (err) {
      console.error("Erro ao exportar CSV:", err);
      Alert.alert("Erro", "Falha ao exportar CSV.");
    }
  }

  async function importarCSV() {
    if (!isEventoIdValido(eventoId)) {
      Alert.alert("Erro", "Evento inválido.");
      return;
    }

    const result = await DocumentPicker.getDocumentAsync({
      type: [
        "text/csv",
        "text/comma-separated-values",
        "application/csv",
        "application/vnd.ms-excel",
      ],
      copyToCacheDirectory: true,
    });

    if (result.canceled) return;

    const file = result.assets?.[0];

    if (!file?.uri) {
      Alert.alert("Erro", "Arquivo inválido.");
      return;
    }

    console.log("Arquivo selecionado:", file);

    try {
      setLoading(true);

      const formData = new FormData();
      const fileName = file.name ?? "participantes.csv";
      const mimeType = file.mimeType ?? "text/csv";

      if (file.uri.startsWith("data:")) {
        const response = await fetch(file.uri);
        const blob = await response.blob();
        formData.append("arquivo", blob, fileName);
      } else {
        formData.append("arquivo", {
          uri: file.uri,
          name: fileName,
          type: mimeType,
        } as any);
      }

      formData.append("evento_id", String(eventoId));

      const response = await fetch(`${API_BASE}/importar_participantes.php?debug=1`, {
        method: "POST",
        body: formData,
      });

      const text = await response.text();
      console.log("IMPORTAÇÃO STATUS:", response.status);
      console.log("IMPORTAÇÃO BRUTA:", JSON.stringify(text));

      const data = parseApiResponse(text);

      if (!response.ok) {
        Alert.alert(
          "Erro",
          data?.msg ?? data?.message ?? `Erro HTTP ${response.status}`
        );
        return;
      }

      if (!data || typeof data !== "object") {
        Alert.alert("Erro", "Resposta inválida do servidor.");
        return;
      }

      if (data.status === "ok" || data.success === true) {
        const resumo = data.resumo;
        const detalhes = resumo
          ? `\n\nLinhas lidas: ${resumo.linhas_lidas ?? 0}\nUsuários criados: ${resumo.usuarios_criados ?? 0}\nUsuários existentes: ${resumo.usuarios_existentes ?? 0}\nInscrições criadas: ${resumo.inscricoes_criadas ?? 0}\nIgnoradas: ${resumo.linhas_ignoradas ?? 0}`
          : "";

        Alert.alert("Sucesso", `Importação concluída!${detalhes}`);
        await carregarPessoas();
        return;
      }

      Alert.alert("Erro", data.msg ?? data.message ?? "Erro na importação");
    } catch (err) {
      console.error("Erro fetch:", err);
      Alert.alert("Erro", "Falha ao importar CSV");
    } finally {
      setLoading(false);
    }
  }

  return (
    <ScrollView className="flex-1 dark:bg-[#121212]">
      <Mainframe name={eventoNome} link="www.evento.com">
        <View className="px-8">
          <Text className="text-2xl dark:text-white">Pessoas</Text>

          <View className="flex-row items-center gap-2 mt-4">
            <Pressable
              className="w-40 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-2"
              onPress={() => setShowActions((value) => !value)}
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

          <View className="flex-row items-center gap-2 my-4 border border-slate-500 rounded-lg p-1">
            <TextInput
              value={busca}
              onChangeText={setBusca}
              placeholder="Buscar por Nome ou Email..."
              autoCapitalize="none"
              autoCorrect={false}
              className="flex-1 text-lg dark:text-white"
            />
            <Ionicons name="search" size={22} />
          </View>

          {loading && <ActivityIndicator size="large" color="#9BEC00" />}

          <View className="flex-row flex-wrap gap-4 mt-4">
            {pessoasFiltradas.map((pessoa, index) => (
              <View
                key={`${pessoa.email || pessoa.nome}-${index}`}
                className="border rounded-lg p-4 w-64 bg-white"
              >
                <Text className="font-bold">{pessoa.nome || "Sem nome"}</Text>
                <Text>{pessoa.email}</Text>
              </View>
            ))}
          </View>
        </View>
      </Mainframe>
    </ScrollView>
  );
}
