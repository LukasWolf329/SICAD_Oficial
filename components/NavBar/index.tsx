import Ionicons from '@expo/vector-icons/build/Ionicons';
import AsyncStorage from "@react-native-async-storage/async-storage";
import { router, useNavigation } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { Dimensions, Image, Linking, Modal, Pressable, ScrollView, Text, TextInput, View } from 'react-native';


interface InputProps {
  label: string;
  placeholder?: string;
  note?: string;
}

interface Evento {
  evento_id: number;
  evento_nome: string;
  data_inicio: string;
  data_fim: string;
  status_usuario: string;
  total_participantes: number | null;
}

export function NavBar() {
  const [nome, setNome] = useState("");
  const [showEventos, setShowEventos] = useState(false);
  const navigation = useNavigation();
  const [evento, setEvento] = useState<Evento[]>([]);



  async function carregarUsuario() {
    const nomeSalvo = await AsyncStorage.getItem("userName");
    if (nomeSalvo) {
      setNome(nomeSalvo);
    }
  }

  useEffect(() => {
    carregarUsuario();

    const unsubscribe = navigation.addListener("focus", () => {
      carregarUsuario();
    });

    return unsubscribe;
  }, [navigation]);

  useEffect(() => {
    async function carregarEvento() {
      try {
        const userId = await AsyncStorage.getItem("userId");
        const response = await fetch(
          "http://200.18.141.92/SICAD/get_dropdown_eventos.php",
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              userId: Number(userId),
            }),
          }
        );

        const data = await response.json();
        console.log("EVENTOS DO BACKEND:", data.eventos);

        setEvento(data.eventos || []);

      } catch (error) {
        console.error("Erro ao carregar eventos:", error);
      }
    }

    carregarEvento();
  }, []);

  function abrirEvento(eventoId: number) {
    setShowEventos(false);
    router.push({
      pathname: "/(tabs)/(painel)/home/page",
      params: { id: eventoId },
    });
  }

  return (
    <View className="flex-row justify-between items-center px-4 bg-[#059212]">
      {/* LOGO */}
      <View className="p-2">
        <Image source={require("../../assets/images/logo-composta-branca.png")} />
      </View>

      {/* MENU PRINCIPAL */}
      <View className="flex-row items-center gap-6">
        {/* Botão Meus eventos */}
        <Pressable
          onPress={() => setShowEventos(true)}
          className="flex-row items-center"
        >
          <Text className="text-white text-base">Meus eventos </Text>
          <Ionicons name="caret-down-outline" size={20} color="white" />
        </Pressable>

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
          <Pressable className="flex-row items-center">
            <Text className="text-white text-base">{nome}</Text>
            <Ionicons name="caret-down-outline" size={20} color="white" />
          </Pressable>
        </View>
      </View>

      {/* MODAL DE MEUS EVENTOS */}
      <Modal
        visible={showEventos}
        animationType="fade"
        transparent={true}
        onRequestClose={() => setShowEventos(false)}
      >
        <View className="flex-1 bg-black/40 justify-center items-center">
          <View className="w-11/12 md:w-2/3 bg-white rounded-2xl shadow-xl p-4 max-h-[80%]">
            {/* Cabeçalho */}
            <View className="flex-row justify-between items-center border-b pb-2 mb-3">
              <Text className="text-blue-600 text-lg font-semibold">Meus Eventos</Text>
              <Pressable onPress={() => setShowEventos(false)}>
                <Ionicons name="close" size={24} color="#333" />
              </Pressable>
            </View>

            {/* Filtro e busca */}
            <View className="flex-row items-center gap-2 mb-3">
              <View className="border rounded-lg px-3 py-2">
                <Text>Todos ▼</Text>
              </View>
              <TextInput
                placeholder="Busque meus eventos..."
                className="flex-1 border rounded-lg px-3 py-2"
              />
              <Ionicons name="search" size={22} color="#555" />
            </View>

            {/* Lista de eventos */}
            <ScrollView showsVerticalScrollIndicator={false}>
              {evento.map((ev, i) => (
                <Pressable
                  key={i}
                  className="flex-row items-center border rounded-xl p-3 mb-2 bg-gray-50"
                  style={{ cursor: 'pointer' }}
                  onPress={() => abrirEvento(ev.evento_id)}
                >
                  <View className="w-16 h-10 bg-gray-300 rounded-md mr-3" />
                  <View className="flex-1">
                    <Text className="font-semibold text-gray-800">{ev.evento_nome}</Text>
                    <Text className="text-gray-600 text-sm">
                      {ev.status_usuario === "participante" && (
                        <>
                          Participante - {ev.data_inicio} - Inscrito
                        </>
                      )}
                      {ev.status_usuario === "organizador" && (
                        <>
                          Organizador - {ev.data_inicio} - {ev.total_participantes} participantes
                        </>
                      )}
                    </Text>
                  </View>
                </Pressable>
              ))}

              {/* Botão adicionar */}
              <Pressable className="border-t pt-3 mt-2 flex-row justify-center">
                <Text className="text-blue-600 font-semibold">+ Adicionar</Text>
              </Pressable>
            </ScrollView>
          </View>
        </View>
      </Modal>
    </View>
  );
}


export function SideBar({ children }: { children?: React.ReactNode }) {
  const screenWidth = Dimensions.get("window").width;
  const isSmall = screenWidth < 768;
  const [isOpen, setIsOpen] = React.useState(!isSmall);

  React.useEffect(() => {
    setIsOpen(!isSmall); // reabre sidebar em desktop
  }, [screenWidth]);

  return (
    <>
      {/* Botão Hamburger apenas em telas pequenas */}
      {isSmall && (
        <Pressable
          onPress={() => setIsOpen(!isOpen)}
          className="absolute top-4 left-4 z-20 p-2 bg-[#059212] rounded"
        >
          <Ionicons name={isOpen ? "close" : "menu"} size={28} color="white" />
        </Pressable>
      )}

      {/* Sidebar */}
      {isOpen && (
        <View
          className={`h-full p-4 ${isSmall
            ? "absolute left-0 top-0 w-[70%] z-10 shadow-lg"
            : "w-[250px]"
            }`}
        >
          {children}
        </View>
      )}
    </>
  );
}

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface SideBarCategoryItem {
  nome: string;
  icone: IoniconName;
  link: string;
}

interface SideBarCategoryProps {
  titulo: string;
  itens: SideBarCategoryItem[];
}

export function SideBarCategory({ titulo, itens }: SideBarCategoryProps) {
  return (
    <View>
      <Text className='text-2xl color-slate-600 dark:color-white font-bold mb-4'>{titulo}</Text>
      <View>
        {itens.map((item, idx) => (
          <Pressable
            key={idx}
            className='flex-row items-center mb-4 mx-4 color-slate-500 hover:color-white'
            onPress={() => router.push(item.link as any)}
          >
            <Text className="text-2xl ml-1 color-slate-400 hover:color-black dark:hover:color-white">
              <Ionicons name={item.icone} size={24} className='mr-2' />
              {item.nome}
            </Text>
          </Pressable>
        ))}
      </View>
    </View>
  );
}

export function Mainframe({ children, name, photoUrl, link }: { children?: React.ReactNode; name: string; photoUrl?: string; link?: string }) {
  const screenHeight = Dimensions.get('window').height;
  const screenWidth = Dimensions.get('window').width;
  return (
    <View className="bg-white dark:bg-[#242424] p-4 pb-20">
      <View className='flex-row m-8 items-center'>
        <Image source={require('../../assets/images/user.png')} style={{ width: 80, height: 80 }} className='rounded-full' />
        <View>
          <Text className='text-2xl mx-4 font-semibold dark:color-[#e0e0e0]'>{name}</Text>
          {link != null && (
            <Pressable onPress={() => Linking.openURL(`https://${link}`)}>
              <Text className='text-sky-500 mx-4 font-semibold'>{link}</Text>
            </Pressable>
          )}
        </View>
      </View>
      {children}
    </View>
  );
}


