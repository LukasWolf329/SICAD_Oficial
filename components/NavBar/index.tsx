import { logout } from '@/app/utils/logout';
import Ionicons from '@expo/vector-icons/build/Ionicons';
import AsyncStorage from "@react-native-async-storage/async-storage";
import { router, useNavigation } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
  Dimensions,
  Image,
  Linking,
  Modal,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View
} from 'react-native';
import * as DocumentPicker from "expo-document-picker";
import { useEventosModal } from './EventosModalContext';
import NiceAlert from '../NiceAlert/NiceAlert';

/* ------------------------------------------------------
   TIPAGENS
-------------------------------------------------------*/
interface Evento {
  evento_id: number;
  evento_nome: string;
  data_inicio: string;
  data_fim: string;
  status_usuario: string;
  total_participantes: number | null;
}

type NiceAlertVariant = "error" | "info" | "success";

type NiceAlertState = {
  visible: boolean;
  title: string;
  message: string;
  variant: NiceAlertVariant;
};


const API_GET_EVENTOS =
  "https://sicad.linceonline.com.br/controller/get_dropdown_eventos.php";

const API_IMPORTAR_JSON =
  "https://sicad.linceonline.com.br/controller/importar_eventos_json_arquivo.php";

function parseApiResponse(raw: unknown) {
  if (typeof raw !== "string") return raw;

  const texto = raw.trim();

  try {
    return JSON.parse(texto);
  } catch {
    const inicioJson = texto.indexOf("{");
    if (inicioJson >= 0) {
      try {
        return JSON.parse(texto.slice(inicioJson));
      } catch {
        return null;
      }
    }
    return null;
  }
}


/* ------------------------------------------------------
   NAVBAR
-------------------------------------------------------*/
export function NavBar() {
  const navigation = useNavigation();

  const [nome, setNome] = useState("");
  const [evento, setEvento] = useState<Evento[]>([]);
  const { showEventos, openEventos, closeEventos } = useEventosModal();
  const [showPerfil, setShowPerfil] = useState(false);

  const [importandoJson, setImportandoJson] = useState(false);

  const [niceAlert, setNiceAlert] = useState<NiceAlertState>({
    visible: false,
    title: "",
    message: "",
    variant: "info",
  });

  function mostrarNiceAlert(
    title: string,
    message: string,
    variant: NiceAlertVariant = "info"
  ) {
    setNiceAlert({
      visible: true,
      title,
      message,
      variant,
    });
  }

  function fecharNiceAlert() {
    setNiceAlert((prev) => ({
      ...prev,
      visible: false,
    }));
  }
  /* ---------------- Carregar nome do usuário ---------------- */
  async function carregarUsuario() {
    const nomeSalvo = await AsyncStorage.getItem("userName");
    if (nomeSalvo) setNome(nomeSalvo);
  }

  useEffect(() => {
    carregarUsuario();
    const unsubscribe = navigation.addListener("focus", carregarUsuario);
    return unsubscribe;
  }, [navigation]);

  /* ---------------- Carregar eventos do backend ---------------- */
  /* ---------------- Carregar eventos do backend ---------------- */
  async function carregarEvento() {
    try {
      const userId = await AsyncStorage.getItem("userId");
      console.log("userId:", userId);

      if (!userId) {
        setEvento([]);
        return;
      }

      const response = await fetch(API_GET_EVENTOS, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ userId: Number(userId) }),
      });

      const raw = await response.text();
      console.log("dropdown status:", response.status);
      console.log("dropdown content-type:", response.headers.get("content-type"));
      console.log("RAW dropdown:", JSON.stringify(raw));

      if (!response.ok) {
        console.error("HTTP ERROR dropdown:", response.status, raw);
        setEvento([]);
        return;
      }

      const data: any = parseApiResponse(raw);

      if (!data || typeof data !== "object") {
        console.error("get_dropdown_eventos.php não retornou JSON válido");
        setEvento([]);
        return;
      }

      const lista = Array.isArray(data) ? data : data.eventos ?? [];

      const normalizada = lista.map((ev: any) => ({
        evento_id: Number(ev.evento_id ?? ev.codigo ?? 0),
        evento_nome: String(ev.evento_nome ?? ev.nome ?? "Evento"),
        data_inicio: String(ev.data_inicio ?? ""),
        data_fim: String(ev.data_fim ?? ""),
        status_usuario: String(ev.status_usuario ?? ev.tipo_vinculo ?? ""),
        total_participantes:
          ev.total_participantes === null || ev.total_participantes === undefined
            ? null
            : Number(ev.total_participantes),
      }));

      setEvento(normalizada);
    } catch (error) {
      console.error("Erro ao carregar eventos:", error);
      setEvento([]);
    }
  }

  useEffect(() => {
    carregarEvento();
  }, []);

  async function importarJsonPeloBotaoAdicionar() {
    if (importandoJson) return;

    try {
      setImportandoJson(true);

      const resultado = await DocumentPicker.getDocumentAsync({
        type: ["application/json", "text/json", "text/plain"],
        multiple: false,
        copyToCacheDirectory: true,
      });

      if (resultado.canceled) {
        return;
      }

      const arquivo = resultado.assets?.[0];

      if (!arquivo) {
        throw new Error("Nenhum arquivo foi selecionado.");
      }

      const nomeArquivo = arquivo.name || "eventos.json";

      if (!nomeArquivo.toLowerCase().endsWith(".json")) {
        throw new Error("Selecione um arquivo com extensão .json.");
      }

      const userId = await AsyncStorage.getItem("userId");

      if (!userId) {
        throw new Error("Usuário não identificado. Faça login novamente.");
      }

      const formData = new FormData();

      /*
        No Web, o DocumentPicker pode retornar um File em arquivo.file.
        No Android/iOS, normalmente usamos uri/name/type.
      */
      const arquivoWeb = (arquivo as any).file;

      if (arquivoWeb) {
        formData.append("arquivo_json", arquivoWeb, nomeArquivo);
      } else {
        formData.append("arquivo_json", {
          uri: arquivo.uri,
          name: nomeArquivo,
          type: arquivo.mimeType || "application/json",
        } as any);
      }

      formData.append("userId", String(userId));

      const response = await fetch(API_IMPORTAR_JSON, {
        method: "POST",
        body: formData,
      });

      const raw = await response.text();

      console.log("importar JSON status:", response.status);
      console.log("RAW importar JSON:", raw);

      const data: any = parseApiResponse(raw);

      if (!response.ok) {
        throw new Error(
          data?.erro ||
          data?.message ||
          raw ||
          `Erro HTTP ${response.status}`
        );
      }

      if (!data || data.ok !== true) {
        throw new Error(data?.erro || "O backend não retornou sucesso.");
      }

      await carregarEvento();

      const resultadoImportacao = data.resultado ?? {};

      mostrarNiceAlert(
        "Importação concluída",
        `Eventos: ${resultadoImportacao.total_eventos ?? 0}\n` +
        `Atividades: ${resultadoImportacao.total_atividades ?? 0}\n` +
        `Participantes vinculados: ${resultadoImportacao.total_participantes_vinculados ?? 0
        }`,
        "success"
      );
    } catch (error: any) {
      console.error("Erro ao importar JSON:", error);

      mostrarNiceAlert(
        "Erro ao importar JSON",
        error?.message || "Não foi possível importar o arquivo JSON.",
        "error"
      );
    } finally {
      setImportandoJson(false);
    }
  }

  /* ---------------- Abrir evento com params ---------------- */
  async function abrirEvento(eventoId: number) {
    closeEventos();

    const userId = await AsyncStorage.getItem("userId");
    const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
    await AsyncStorage.setItem(key, String(eventoId));

    router.replace({
      pathname: "/(tabs)/(painel)/home/page",
      params: { id: String(eventoId) },
    });
  }

  /* ------------------------------------------------------
       RENDER
  -------------------------------------------------------*/
  return (
    <View className="flex-row justify-between items-center px-4 bg-[#059212]">

      {/* LOGO */}
      <View className="p-2">
        <Image source={require("../../assets/images/logo-composta-branca.png")} />
      </View>

      {/* MENU PRINCIPAL */}
      <View className="flex-row items-center gap-6">

        {/* Botão Meus eventos */}
        <Pressable onPress={openEventos} className="flex-row items-center">
          <Text className="text-white text-base">Meus eventos </Text>
          <Ionicons name="caret-down-outline" size={20} color="white" />
        </Pressable>

        {/* Área do Organizador */}
        <Pressable className="flex-row items-center">
          <Text className="text-white text-base">Área do Organizador </Text>
          <Ionicons name="caret-down-outline" size={20} color="white" />
        </Pressable>

        {/* Perfil */}
        <View className="flex-row items-center gap-2">
          <Image
            source={require("../../assets/images/favicon.png")}
            style={{ width: 40, height: 40, borderRadius: 50 }}
          />
          <Pressable onPress={() => setShowPerfil(true)} className="flex-row items-center">
            <Text className="text-white text-base">{nome}</Text>
            <Ionicons name="caret-down-outline" size={20} color="white" />
          </Pressable>
        </View>
      </View>

      {/* ---------------- MODAL MEUS EVENTOS ---------------- */}
      <Modal visible={showEventos} animationType="fade" transparent onRequestClose={closeEventos}>
        <View className="flex-1 bg-black/40 justify-center items-center">
          <View className="w-11/12 md:w-2/3 bg-white rounded-2xl shadow-xl max-h-[80%]">

            {/* Cabeçalho */}
            <View className="bg-[#2192FF] rounded-t-2xl flex-row justify-between items-center px-4 py-3">
              <Ionicons name="browsers-outline" size={22} color="#fff" />
              <Text className="text-white text-lg font-semibold">Meus Eventos</Text>
              <Pressable onPress={closeEventos}>
                <Ionicons name="close-outline" size={24} color="#fff" />
              </Pressable>
            </View>

            {/* Filtro e busca */}
            <View className="flex-row items-center gap-2 mb-3 p-4">
              <View className="border rounded-lg px-3 py-2 flex-row items-center gap-1">
                <Text className="font-semibold">Todos</Text>
                <Ionicons name="chevron-down-outline" size={22} />
              </View>

              <TextInput placeholder="Busque meus eventos..." className="flex-1 border rounded-lg px-3 py-2" />

              <View className="border rounded-lg px-3 py-2 flex-row items-center">
                <Ionicons name="search" size={22} color="#555" />
              </View>
            </View>

            {/* Lista */}
            <ScrollView className="px-4" showsVerticalScrollIndicator={false}>
              {evento.map((ev, i) => (
                <Pressable
                  key={i}
                  onPress={() => abrirEvento(ev.evento_id)}
                  className="flex-row items-center border rounded-xl p-3 mb-2 bg-gray-50"
                  style={{ cursor: "pointer" }}
                >
                  <View className="w-16 h-10 bg-gray-300 rounded-md mr-3" />

                  <View className="flex-1">
                    <Text className="font-semibold text-gray-800">{ev.evento_nome}</Text>

                    <Text className="text-gray-600 text-sm">
                      {ev.status_usuario === "participante" &&
                        `Participante – ${ev.data_inicio} – Inscrito`}

                      {ev.status_usuario === "organizador" &&
                        `Organizador – ${ev.data_inicio} – ${ev.total_participantes} participantes`}
                    </Text>
                  </View>
                </Pressable>
              ))}

              {/* Botão adicionar */}
              <Pressable
                onPress={importarJsonPeloBotaoAdicionar}
                disabled={importandoJson}
                className="border-t py-3 mt-2 flex-row justify-center items-center"
                style={{ opacity: importandoJson ? 0.6 : 1 }}
              >
                <Ionicons
                  name={importandoJson ? "cloud-upload-outline" : "add-outline"}
                  size={20}
                  color="#2192FF"
                />

                <Text className="text-[#2192FF] font-semibold">
                  {importandoJson ? " Importando JSON..." : " Adicionar"}
                </Text>
              </Pressable>
            </ScrollView>

          </View>
        </View>
      </Modal>

      {/* ---------------- MODAL PERFIL ---------------- */}
      <Modal visible={showPerfil} animationType="fade" transparent onRequestClose={() => setShowPerfil(false)}>
        <View className="flex-1 bg-black/40 justify-center items-center">
          <View className="w-2/12 bg-white rounded-2xl shadow-xl max-h-[80%]">

            <View className="bg-[#2192FF] rounded-t-2xl flex-row justify-between items-center px-4 py-3">
              <Ionicons name="person-circle-outline" size={22} color="#fff" />
              <Text className="text-white text-lg font-semibold">Perfil</Text>
              <Pressable onPress={() => setShowPerfil(false)}>
                <Ionicons name="close-outline" size={24} color="#fff" />
              </Pressable>
            </View>

            <Pressable className="p-3 mt-2 flex-row items-center">
              <Text className="font-semibold">Meu Perfil</Text>
            </Pressable>

            <Pressable className="p-3 mt-2 flex-row items-center">
              <Text className="font-semibold">Meus Eventos</Text>
            </Pressable>

            <Pressable className="p-3 mt-2 flex-row items-center">
              <Text className="font-semibold">Meus Materiais</Text>
            </Pressable>

            <Pressable onPress={logout} className="border-t p-3 mt-2 flex-row items-center rounded-b-2xl">
              <Ionicons name="log-out-outline" size={20} />
              <Text className="font-semibold ml-2">Sair</Text>
            </Pressable>

          </View>
        </View>
      </Modal>
      <NiceAlert
        visible={niceAlert.visible}
        title={niceAlert.title}
        message={niceAlert.message}
        variant={niceAlert.variant}
        onClose={fecharNiceAlert}
      />
    </View>
  );
}

/* ------------------------------------------------------
   SIDEBAR
-------------------------------------------------------*/
export function SideBar({ children }: { children?: React.ReactNode }) {
  const screenWidth = Dimensions.get("window").width;
  const isSmall = screenWidth < 768;

  const [isOpen, setIsOpen] = useState(!isSmall);

  useEffect(() => {
    setIsOpen(!isSmall);
  }, [screenWidth]);

  return (
    <>
      {isSmall && (
        <Pressable
          onPress={() => setIsOpen(!isOpen)}
          className="absolute top-4 left-4 z-20 p-2 bg-[#059212] rounded"
        >
          <Ionicons name={isOpen ? "close" : "menu"} size={28} color="white" />
        </Pressable>
      )}

      {isOpen && (
        <View
          className={`h-full p-4 ${isSmall ? "absolute left-0 top-0 w-[70%] z-10 shadow-lg bg-white" : "w-[250px]"
            }`}
        >
          {children}
        </View>
      )}
    </>
  );
}

/* ------------------------------------------------------
   SIDEBAR CATEGORY
-------------------------------------------------------*/
type IoniconName = React.ComponentProps<typeof Ionicons>["name"];

interface SideBarItemProps {
  nome: string;
  icone: IoniconName;
  link: string;
}

interface SideBarCategoryProps {
  titulo: string;
  itens: SideBarItemProps[];
}

export function SideBarCategory({ titulo, itens }: SideBarCategoryProps) {
  return (
    <View>
      <Text className="text-2xl color-slate-600 dark:color-white font-bold mb-4">
        {titulo}
      </Text>

      {itens.map((item, idx) => (
        <Pressable
          key={idx}
          className="flex-row items-center mb-4 mx-4"
          onPress={() => router.push(item.link as any)}
        >
          <Ionicons name={item.icone} size={24} className="mr-2 dark:color-white" />
          <Text className="text-xl ml-2 dark:color-white">{item.nome}</Text>
        </Pressable>
      ))}
    </View>
  );
}

/* ------------------------------------------------------
   MAINFRAME
-------------------------------------------------------*/
export function Mainframe({
  children,
  name,
  link,
}: {
  children?: React.ReactNode;
  name: string;
  link?: string;
}) {
  return (
    <View className="bg-white dark:bg-[#242424] p-4 pb-20">
      <View className="flex-row m-8 items-center">
        <Image
          source={require("../../assets/images/user.png")}
          style={{ width: 80, height: 80 }}
          className="rounded-full"
        />

        <View>
          <Text className="text-2xl mx-4 font-semibold dark:color-[#e0e0e0]">
            {name}
          </Text>

          {link && (
            <Pressable onPress={() => Linking.openURL(`https://${link}`)}>
              <Text className="text-sky-500 mx-4 font-semibold">{link}</Text>
            </Pressable>
          )}
        </View>
      </View>

      {children}
    </View>
  );
}
