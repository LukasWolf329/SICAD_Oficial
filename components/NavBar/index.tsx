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

/* ------------------------------------------------------
   NAVBAR
-------------------------------------------------------*/
export function NavBar() {
  const navigation = useNavigation();

  const [nome, setNome] = useState("");
  const [evento, setEvento] = useState<Evento[]>([]);
  const [showEventos, setShowEventos] = useState(false);
  const [showPerfil, setShowPerfil] = useState(false);

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
  useEffect(() => {
    async function carregarEvento() {
      try {
        const userId = await AsyncStorage.getItem("userId");

        const response = await fetch(
          "http://200.18.141.92/SICAD/get_dropdown_eventos.php",
          {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ userId: Number(userId) }),
          }
        );

        const data = await response.json();
        setEvento(data.eventos || []);

      } catch (error) {
        console.error("Erro ao carregar eventos:", error);
      }
    }

    carregarEvento();
  }, []);

  /* ---------------- Abrir evento com params ---------------- */
  function abrirEvento(eventoId: number) {
    setShowEventos(false);

    router.push({
      pathname: "/(tabs)/(painel)/home/page",
      params: { id: eventoId },
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
        <Pressable onPress={() => setShowEventos(true)} className="flex-row items-center">
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
      <Modal visible={showEventos} animationType="fade" transparent onRequestClose={() => setShowEventos(false)}>
        <View className="flex-1 bg-black/40 justify-center items-center">
          <View className="w-11/12 md:w-2/3 bg-white rounded-2xl shadow-xl max-h-[80%]">

            {/* Cabeçalho */}
            <View className="bg-[#2192FF] rounded-t-2xl flex-row justify-between items-center px-4 py-3">
              <Ionicons name="browsers-outline" size={22} color="#fff" />
              <Text className="text-white text-lg font-semibold">Meus Eventos</Text>
              <Pressable onPress={() => setShowEventos(false)}>
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
              <Pressable className="border-t py-3 mt-2 flex-row justify-center items-center">
                <Ionicons name="add-outline" size={20} color="#2192FF" />
                <Text className="text-[#2192FF] font-semibold"> Adicionar</Text>
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

            <Pressable className="border-t p-3 mt-2 flex-row items-center rounded-b-2xl">
              <Ionicons name="log-out-outline" size={20} />
              <Text className="font-semibold ml-2">Sair</Text>
            </Pressable>

          </View>
        </View>
      </Modal>
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
          className={`h-full p-4 ${
            isSmall ? "absolute left-0 top-0 w-[70%] z-10 shadow-lg bg-white" : "w-[250px]"
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
          <Ionicons name={item.icone} size={24} className="mr-2" />
          <Text className="text-xl ml-2">{item.nome}</Text>
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
