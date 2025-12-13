import "../../../../style/global.css";

import { Ionicons } from '@expo/vector-icons';
import React, { useEffect, useState } from "react";
import { Pressable, ScrollView, Text, View } from 'react-native';

import { InfoBox } from "@/components/InfoBox";
import { useLocalSearchParams } from "expo-router";
import { Mainframe } from '../../../../components/NavBar';

export default function HomePage() {
    const { id } = useLocalSearchParams();
    const [totalInscritos, setTotalInscritos] = useState<number>(0);
    const [atividadesCadastradas, setAtividadesCadastradas] = useState<number>(0);
    const [totalCertificados, setTotalCertificados] = useState<number>(0);

    const [eventoNome, setEventoNome] = useState<string>("");

    useEffect(() => {
        fetch("http://200.18.141.92/SICAD/page-org.php",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    evento_id: Number(id),
                }),
            }
        )
            .then((res) => res.json())
            .then((json) => {
                setEventoNome(json.evento_nome ?? "Evento");
                setTotalInscritos(json.total_participantes ?? 0);
                setAtividadesCadastradas(json.atividades_cadastradas ?? 0);
                setTotalCertificados(json.total_certificados ?? 0);
            })
            .catch((err) => console.error("Erro ao buscar os dados:", err))
    }, []);
    return (
        <ScrollView className="flex-1 dark:bg-[#121212]">

            <Mainframe name={eventoNome} photoUrl="user.png" link="www.evento.com">
                <View className="flex-row justify-center gap-4">
                    <InfoBox name="Total de Inscritos" icon="people" counter={(totalInscritos ?? 0).toString()}></InfoBox>
                    <InfoBox name="Certificados Emitidos" icon="card-outline" counter={(totalCertificados ?? 0).toString()}></InfoBox>
                    <InfoBox name="Total de Inscritos" icon="book-outline" counter={(atividadesCadastradas ?? 0).toString()}></InfoBox>
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
                            <Ionicons name="checkmark-circle" size={48} className="color-green-600" />
                            <Text className="text-xl dark:color-white">Cadastrar Entradas</Text>
                        </View>
                        <View>
                            <Pressable className="border dark:border-[#e0e0e0] rounded-xl px-4 py-2 w-min text-xl dark:color-[#e0e0e0]">Acesse</Pressable>
                        </View>
                    </View>
                    <View className="flex-row justify-between items-center mb-8">
                        <View className="flex-row items-center gap-2">
                            <Ionicons name="checkmark-circle" size={48} className="color-green-600" />
                            <Text className="text-xl dark:color-white">Configurar Certificados</Text>
                        </View>
                        <View>
                            <Pressable className="border dark:border-[#e0e0e0] rounded-xl px-4 py-2 w-min text-xl dark:color-[#e0e0e0]">Acesse</Pressable>
                        </View>
                    </View>
                    <View className="flex-row justify-between items-center mb-8">
                        <View className="flex-row items-center gap-2">
                            <Ionicons name="checkmark-circle" size={48} className="color-slate-300" />
                            <Text className="text-xl dark:color-white">Evento Publicado</Text>
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

